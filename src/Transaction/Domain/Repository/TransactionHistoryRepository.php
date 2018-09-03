<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;

class TransactionHistoryRepository extends BaseEntityRepository
{
    /**
     * @return TransactionHistoryRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(TransactionHistoryEntity::class);
    }
}
