<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;

class TransactionHistoryRepository extends BaseEntityRepository
{
    /**
     * @param string $transaction_id
     * @return TransactionHistoryEntity[]
     */
    public function findByTransactionId(string $transaction_id): array
    {
        $qb = $this->createQueryBuilder('th');

        $qb->join('th.transaction', 't')
            ->where($qb->expr()->eq('t.id', $transaction_id));

        return $qb->getQuery()->getResult();
    }

    /**
     * @return TransactionHistoryRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository(): self
    {
        return new self(TransactionHistoryEntity::class);
    }
}
