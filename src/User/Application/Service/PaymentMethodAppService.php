<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\PaymentMethodConstant;
use RidiPay\User\Domain\Repository\CardPaymentKeyRepository;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;

class PaymentMethodAppService
{
    /**
     * @param int $u_idx
     * @return PaymentMethodEntity[]
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getAvailablePaymentMethods(int $u_idx): array
    {
        try {
            UserAppService::validateUser($u_idx);
        } catch (LeavedUserException | NotFoundUserException $e) {
            return [];
        }

        return PaymentMethodRepository::getRepository()->getAvailablePaymentMethods($u_idx);
    }

    /**
     * @param int $payment_method_id
     * @return PaymentMethodEntity
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPaymentMethod(int $payment_method_id): PaymentMethodEntity
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneById($payment_method_id);
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }

        return $payment_method;
    }

    /**
     * @param string $payment_method_uuid
     * @return PaymentMethodEntity
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPaymentMethodByUuid(string $payment_method_uuid): PaymentMethodEntity
    {
        $payment_method = PaymentMethodRepository::getRepository()
            ->findOneByUuid(Uuid::fromString($payment_method_uuid));
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }

        return $payment_method;
    }

    /**
     * @param int $payment_method_id
     * @return string
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     */
    public static function getOneTimePaymentKey(int $payment_method_id): string
    {
        $card_payment_key = CardPaymentKeyRepository::getRepository()
            ->findOneByCardIdAndPurpose($payment_method_id, PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_ONE_TIME);
        if ($card_payment_key === null) {
            throw new UnregisteredPaymentMethodException();
        }
        if ($card_payment_key->getCard()->isDeleted()) {
            throw new DeletedPaymentMethodException();
        }

        return $card_payment_key->getPaymentKey();
    }

    /**
     * @param int $payment_method_id
     * @return string
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     */
    public static function getBillingPaymentKey(int $payment_method_id): string
    {
        $card_payment_key = CardPaymentKeyRepository::getRepository()
            ->findOneByCardIdAndPurpose($payment_method_id, PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_BILLING);
        if ($card_payment_key === null) {
            throw new UnregisteredPaymentMethodException();
        }
        if ($card_payment_key->getCard()->isDeleted()) {
            throw new DeletedPaymentMethodException();
        }

        return $card_payment_key->getPaymentKey();
    }

    /**
     * @param int $u_idx
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function deleteAllPaymentMethods(int $u_idx): void
    {
        $payment_method_repo = PaymentMethodRepository::getRepository();
        $payment_methods = $payment_method_repo->getAvailablePaymentMethods($u_idx);

        $em = EntityManagerProvider::getEntityManager();
        $em->beginTransaction();

        try {
            foreach ($payment_methods as $payment_method) {
                $payment_method->delete();
                $payment_method_repo->save($payment_method);
            }

            $em->commit();
        } catch (\Throwable $t) {
            $em->rollback();
            $em->close();

            throw $t;
        }
    }
}
