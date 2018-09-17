<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\User\Application\Dto\PaymentMethodDto;
use RidiPay\User\Application\Dto\AvailablePaymentMethodsDto;
use RidiPay\User\Application\Dto\PaymentMethodDtoFactory;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;

class PaymentMethodAppService
{
    /**
     * @param int $u_idx
     * @return AvailablePaymentMethodsDto
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getAvailablePaymentMethods(int $u_idx): AvailablePaymentMethodsDto
    {
        $payment_methods = PaymentMethodRepository::getRepository()->getAvailablePaymentMethods($u_idx);

        return new AvailablePaymentMethodsDto($payment_methods);
    }

    /**
     * @param string $payment_method_uuid
     * @return PaymentMethodDto
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPaymentMethodByUuid(string $payment_method_uuid): PaymentMethodDto
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneByUuid(Uuid::fromString($payment_method_uuid));
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }

        return PaymentMethodDtoFactory::create($payment_method);
    }

    /**
     * @param string $payment_method_uuid
     * @return int
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPaymentMethodIdByUuid(string $payment_method_uuid): int
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneByUuid(Uuid::fromString($payment_method_uuid));
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }

        return $payment_method->getId();
    }

    /**
     * @param int $payment_method_id
     * @return string
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getOneTimePaymentPgBillKey(int $payment_method_id): string
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneById($payment_method_id);
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }

        return $payment_method->getCardForOneTimePayment()->getPgBillKey();
    }

    /**
     * @param int $payment_method_id
     * @return bool
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function isCard(int $payment_method_id): bool
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneById($payment_method_id);
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }

        return $payment_method->isCard();
    }
}
