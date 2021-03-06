<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Repository;

use Ramsey\Uuid\UuidInterface;
use RidiPay\Library\BaseEntityRepository;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;

class TransactionRepository extends BaseEntityRepository
{
    /**
     * @param UuidInterface $uuid
     * @return null|TransactionEntity
     */
    public function findOneByUuid(UuidInterface $uuid): ?TransactionEntity
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    /**
     * @param string $partner_transaction_id
     * @return TransactionEntity[]
     */
    public function findByPartnerTransactionId(string $partner_transaction_id): array
    {
        return $this->findBy(['partner_transaction_id' => $partner_transaction_id]);
    }

    /**
     * @return TransactionRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository(): self
    {
        return new self(TransactionEntity::class);
    }
}
