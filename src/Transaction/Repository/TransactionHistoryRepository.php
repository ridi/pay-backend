<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Entity\TransactionHistoryEntity;

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
