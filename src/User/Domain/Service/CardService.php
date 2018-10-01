<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

use RidiPay\Library\EntityManagerProvider;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Service\PgHandlerInterface;
use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\CardAlreadyExistsException;
use RidiPay\User\Domain\Repository\CardIssuerRepository;
use RidiPay\User\Domain\Repository\CardRepository;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;

class CardService
{
    /**
     * @param int $u_idx
     * @param string $card_number 카드 번호 16자리
     * @param string $card_password 카드 비밀번호 앞 2자리
     * @param string $card_expiration_date 카드 유효 기한 (YYMM)
     * @param string $tax_id 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
     * @param int $pg_id
     * @param PgHandlerInterface $pg_handler
     * @return PaymentMethodEntity
     * @throws CardAlreadyExistsException
     * @throws CardRegistrationException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function registerCard(
        int $u_idx,
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id,
        int $pg_id,
        PgHandlerInterface $pg_handler
    ): PaymentMethodEntity {
        self::assertNotHavingCard($u_idx);

        $response = $pg_handler->registerCard($card_number, $card_expiration_date, $card_password, $tax_id);
        $card_issuer = CardIssuerRepository::getRepository()->findOneByPgIdAndCode(
            $pg_id,
            $response->getCardIssuerCode()
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
                $response->getPgBillKey(),
                $card_number
            );
            $card_for_billing_payment = CardEntity::createForBillingPayment(
                $payment_method,
                $card_issuer,
                $pg_id,
                $response->getPgBillKey(),
                $card_number
            );
            $card_repo = CardRepository::getRepository();
            $card_repo->save($card_for_one_time_payment);
            $card_repo->save($card_for_billing_payment);

            $payment_method->setCards($card_for_one_time_payment, $card_for_billing_payment);
            PaymentMethodRepository::getRepository()->save($payment_method);

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }

        return $payment_method;
    }

    /**
     * @param int $u_idx
     * @throws CardAlreadyExistsException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function assertNotHavingCard(int $u_idx): void
    {
        $available_payment_methods = PaymentMethodRepository::getRepository()->getAvailablePaymentMethods(
            $u_idx
        );
        $available_cards = array_filter(
            $available_payment_methods,
            function (PaymentMethodEntity $payment_method) {
                return $payment_method->isCard();
            }
        );

        if (!empty($available_cards)) {
            throw new CardAlreadyExistsException();
        }
    }
}
