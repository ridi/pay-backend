<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\User\Dto\PaymentMethodDto;
use RidiPay\User\Dto\AvailablePaymentMethodsDto;
use RidiPay\User\Dto\PaymentMethodDtoFactory;
use RidiPay\User\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Repository\PaymentMethodRepository;

class PaymentMethodService
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
     * @param int $payment_method_id
     * @return string
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getOneTimePaymentPgBillKey(int $payment_method_id): string
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneById($payment_method_id);

        return $payment_method->getCardForOneTimePayment()->getPgBillKey();
    }

    /**
     * @param string $payment_method_uuid
     * @return PaymentMethodDto
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPaymentMethodByUuid(string $payment_method_uuid): PaymentMethodDto
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneByUuid(Uuid::fromString($payment_method_uuid));

        return PaymentMethodDtoFactory::create($payment_method);
    }

    /**
     * @param string $payment_method_uuid
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPaymentMethodIdByUuid(string $payment_method_uuid): int
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneByUuid(Uuid::fromString($payment_method_uuid));

        return $payment_method->getId();
    }
}
