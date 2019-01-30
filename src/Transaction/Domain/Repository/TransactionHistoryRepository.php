<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;

class TransactionHistoryRepository extends BaseEntityRepository
{
    /**
     * @param int $transaction_id
     * @return TransactionHistoryEntity[]
     */
    public function findByTransactionId(int $transaction_id): array
    {
        return $this->findBy(['transaction_id' => $transaction_id]);
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
