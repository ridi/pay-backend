<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Transaction\Domain\Entity\SubscriptionPaymentMethodHistoryEntity;

class SubscriptionPaymentMethodHistoryRepository extends BaseEntityRepository
{
    /**
     * @return SubscriptionPaymentMethodHistoryRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository(): self
    {
        return new self(SubscriptionPaymentMethodHistoryEntity::class);
    }
}