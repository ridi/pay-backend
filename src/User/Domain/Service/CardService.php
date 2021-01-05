<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

use Predis\Client;
use RidiPay\Kernel;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\TimeUnitConstant;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Repository\PgRepository;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\User\Domain\Entity\CardPaymentKeyEntity;
use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\Repository\CardIssuerRepository;
use RidiPay\User\Domain\Repository\CardPaymentKeyRepository;
use RidiPay\User\Domain\Repository\CardRepository;

class CardService
{
    /**
     * @param int $u_idx
     * @param string $card_number
     * @param string $card_expiration_date
     * @param string $card_password
     * @param string $tax_id
     * @throws CardRegistrationException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    public static function registerCard(
        int $u_idx,
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ): void {
        $is_dev = Kernel::isDev();
        $pg = PgRepository::getRepository()->findActiveOne();

        $pg_handler = $is_dev
            ? PgHandlerFactory::createWithTest($pg->getName())
            : PgHandlerFactory::create($pg->getName());
        $response = $pg_handler->registerCard($card_number, $card_expiration_date, $card_password, $tax_id);
        if (!$response->isSuccess()) {
            throw new CardRegistrationException($response);
        }

        $pg_handler_with_tax_deduction = $is_dev
            ? PgHandlerFactory::createWithTest($pg->getName())
            : PgHandlerFactory::createWithTaxDeduction($pg->getName());
        $response_with_tax_deduction = $pg_handler_with_tax_deduction
            ->registerCard($card_number, $card_expiration_date, $card_password, $tax_id);
        if (!$response_with_tax_deduction->isSuccess()) {
            throw new CardRegistrationException($response_with_tax_deduction);
        }

        $card_registration_key = self::getCardRegistrationKey($u_idx);
        $redis = self::getRedisClient();
        $redis->hmset(
            $card_registration_key,
            [
                'iin' => substr($card_number, 0, 6),
                'card_issuer_code' => $response->getCardIssuerCode(),
                'pg_id' => $pg->getId(),
                'pg_bill_key' => $response->getPaymentKey(),
                'pg_tax_deduction_bill_key' => $response_with_tax_deduction->getPaymentKey()
            ]
        );
        $redis->expire($card_registration_key, TimeUnitConstant::SEC_IN_HOUR);
    }

    /**
     * @param int $u_idx
     * @return bool
     */
    public static function isCardRegistrationInProgress(int $u_idx): bool
    {
        $card_registration_key = self::getCardRegistrationKey($u_idx);
        $redis = self::getRedisClient();
        $card_registration = $redis->hgetall($card_registration_key);

        return !empty($card_registration);
    }

    /**
     * @param int $u_idx
     * @return CardEntity
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function useRegisteredCard(int $u_idx): CardEntity
    {
        $card_registration_key = self::getCardRegistrationKey($u_idx);
        $redis = self::getRedisClient();
        $card_registration = $redis->hgetall($card_registration_key);

        $pg_id = intval($card_registration['pg_id']);
        $pg = PgRepository::getRepository()->findOneById($pg_id);
        $card_issuer = CardIssuerRepository::getRepository()->findOneByPgIdAndCode(
            $pg_id,
            $card_registration['card_issuer_code']
        );

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $card = new CardEntity($u_idx, $card_issuer, $card_registration['iin']);
            CardRepository::getRepository()->save($card);

            $card_payment_keys = [
                CardPaymentKeyEntity::createForOneTimePayment(
                    $card,
                    $pg,
                    $card_registration['pg_bill_key']
                ),
                CardPaymentKeyEntity::createForOneTimeTaxDeductionPayment(
                    $card,
                    $pg,
                    $card_registration['pg_tax_deduction_bill_key']
                ),
                CardPaymentKeyEntity::createForBillingPayment(
                    $card,
                    $pg,
                    $card_registration['pg_bill_key']
                ),
            ];
            foreach ($card_payment_keys as $card_payment_key) {
                CardPaymentKeyRepository::getRepository()->save($card_payment_key);
            }
            $card->setPaymentKeys($card_payment_keys);
            CardRepository::getRepository()->save($card);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        $redis->del([$card_registration_key]);

        return $card;
    }

    /**
     * @return Client
     */
    private static function getRedisClient(): Client
    {
        return new Client(['host' => getenv('REDIS_HOST', true)]);
    }

    /**
     * @param int $u_idx
     * @return string
     */
    private static function getCardRegistrationKey(int $u_idx): string
    {
        return "card-registration:{$u_idx}";
    }
}
