<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Application\Dto\AvailablePaymentMethodsDto;
use RidiPay\User\Application\Dto\PaymentMethodDto;
use RidiPay\User\Application\Dto\PaymentMethodDtoFactory;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
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
        try {
            UserAppService::validateUser($u_idx);
        } catch (LeavedUserException | NotFoundUserException $e) {
            return new AvailablePaymentMethodsDto([]);
        }

        $payment_methods = PaymentMethodRepository::getRepository()->getAvailablePaymentMethods($u_idx);

        return new AvailablePaymentMethodsDto($payment_methods);
    }

    /**
     * @param int $payment_method_id
     * @return PaymentMethodDto
     * @throws UnregisteredPaymentMethodException
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPaymentMethodById(int $payment_method_id): PaymentMethodDto
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneById($payment_method_id);
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }

        return PaymentMethodDtoFactory::create($payment_method);
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
     * @param int $u_idx
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function deletePaymentMethods(int $u_idx): void
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
