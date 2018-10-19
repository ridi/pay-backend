<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

use Predis\Client;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\TimeUnitConstant;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Repository\PgRepository;
use RidiPay\Pg\Domain\Service\PgHandlerFactory;
use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Repository\CardIssuerRepository;
use RidiPay\User\Domain\Repository\CardRepository;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;

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
     */
    public static function registerCard(
        int $u_idx,
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ): void {
        $pg = PgRepository::getRepository()->findActiveOne();
        $pg_handler = PgHandlerFactory::create($pg->getName());
        $response = $pg_handler->registerCard($card_number, $card_expiration_date, $card_password, $tax_id);

        $card_registration_key = self::getCardRegistrationKey($u_idx);
        $redis = self::getRedisClient();
        $redis->hmset(
            $card_registration_key,
            [
                'iin' => substr($card_number, 0, 6),
                'card_issuer_code' => $response->getCardIssuerCode(),
                'pg_id' => $pg->getId(),
                'pg_bill_key' => $response->getPgBillKey()
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
     * @return PaymentMethodEntity
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function useRegisteredCard(int $u_idx): PaymentMethodEntity
    {
        $card_registration_key = self::getCardRegistrationKey($u_idx);
        $redis = self::getRedisClient();
        $card_registration = $redis->hgetall($card_registration_key);

        $pg_id = intval($card_registration['pg_id']);
        $card_issuer = CardIssuerRepository::getRepository()->findOneByPgIdAndCode(
            $pg_id,
            $card_registration['card_issuer_code']
        );

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            $payment_method = PaymentMethodEntity::createForCard($u_idx);
            PaymentMethodRepository::getRepository()->save($payment_method);

            $card_for_one_time_payment = CardEntity::createForOneTimePayment(
                $payment_method,
                $card_issuer,
                $pg_id,
                $card_registration['pg_bill_key'],
                $card_registration['iin']
            );
            $card_for_billing_payment = CardEntity::createForBillingPayment(
                $payment_method,
                $card_issuer,
                $pg_id,
                $card_registration['pg_bill_key'],
                $card_registration['iin']
            );
            $card_repo = CardRepository::getRepository();
            $card_repo->save($card_for_one_time_payment);
            $card_repo->save($card_for_billing_payment);

            $payment_method->setCards($card_for_one_time_payment, $card_for_billing_payment);
            PaymentMethodRepository::getRepository()->save($payment_method);

            UserActionHistoryService::logAddCard($u_idx);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        $redis->del([$card_registration_key]);

        return $payment_method;
    }

    /**
     * @return Client
     */
    private static function getRedisClient(): Client
    {
        return new Client(['host' => getenv('REDIS_HOST')]);
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
