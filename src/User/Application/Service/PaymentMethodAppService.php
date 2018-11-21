<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Service;

use Ramsey\Uuid\Uuid;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Application\Dto\AvailablePaymentMethodsDto;
use RidiPay\User\Application\Dto\PaymentMethodDto;
use RidiPay\User\Application\Dto\PaymentMethodDtoFactory;
use RidiPay\User\Application\Dto\PaymentMethodHistoryItemDto;
use RidiPay\User\Application\Dto\PaymentMethodHistoryItemDtoFactory;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;

class PaymentMethodAppService
{
    /**
     * @param int $payment_method_id
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function validatePaymentMethod(int $payment_method_id): void
    {
        self::getPaymentMethodById($payment_method_id);
    }

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
    public static function getPaymentMethod(int $payment_method_id): PaymentMethodDto
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneById($payment_method_id);
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }

        return PaymentMethodDtoFactory::create($payment_method);
    }

    /**
     * @param string $payment_method_uuid
     * @return int
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getPaymentMethodIdByUuid(string $payment_method_uuid): int
    {
        $payment_method = self::getPaymentMethodByUuid($payment_method_uuid);

        return $payment_method->getId();
    }

    /**
     * @param int $payment_method_id
     * @return int
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getUidxById(int $payment_method_id): int
    {
        $payment_method = self::getPaymentMethodById($payment_method_id);

        return $payment_method->getUidx();
    }

    /**
     * @param int $payment_method_id
     * @return string
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getOneTimePaymentPgBillKey(int $payment_method_id): string
    {
        $payment_method = self::getPaymentMethodById($payment_method_id);

        return $payment_method->getCardForOneTimePayment()->getPgBillKey();
    }

    /**
     * @param int $payment_method_id
     * @return string
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getBillingPaymentPgBillKey(int $payment_method_id): string
    {
        $payment_method = self::getPaymentMethodById($payment_method_id);

        return $payment_method->getCardForBillingPayment()->getPgBillKey();
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

    /**
     * @param int $payment_method_id
     * @return PaymentMethodEntity
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getPaymentMethodById(int $payment_method_id): PaymentMethodEntity
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneById($payment_method_id);
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }
        if ($payment_method->isDeleted()) {
            throw new DeletedPaymentMethodException();
        }

        return $payment_method;
    }

    /**
     * @param string $payment_method_uuid
     * @return PaymentMethodEntity
     * @throws DeletedPaymentMethodException
     * @throws UnregisteredPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getPaymentMethodByUuid(string $payment_method_uuid): PaymentMethodEntity
    {
        $payment_method = PaymentMethodRepository::getRepository()->findOneByUuid(Uuid::fromString($payment_method_uuid));
        if (is_null($payment_method)) {
            throw new UnregisteredPaymentMethodException();
        }
        if ($payment_method->isDeleted()) {
            throw new DeletedPaymentMethodException();
        }

        return $payment_method;
    }

    /**
     * @param int $u_idx
     * @return PaymentMethodHistoryItemDto[]
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getCardsHistory(int $u_idx): array
    {
        $cards = PaymentMethodRepository::getRepository()->findCardsByUidx($u_idx);

        return self::getPaymentMethodsHistory($cards);
    }

    /**
     * @param PaymentMethodEntity[] $payment_methods
     * @return array
     * @throws UnsupportedPaymentMethodException
     */
    private static function getPaymentMethodsHistory(array $payment_methods): array
    {
        $history = [];

        foreach ($payment_methods as $payment_method) {
            $history[] = PaymentMethodHistoryItemDtoFactory::createWithRegistration($payment_method);
            if ($payment_method->isDeleted()) {
                $history[] = PaymentMethodHistoryItemDtoFactory::createWithDeletion($payment_method);
            }
        }

        // 최신 순 정렬
        usort($history, function (PaymentMethodHistoryItemDto $a, PaymentMethodHistoryItemDto $b) {
            return $a->processed_at < $b->processed_at;
        });

        return $history;
    }
}
