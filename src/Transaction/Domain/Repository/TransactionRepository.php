<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Repository;

use Ramsey\Uuid\UuidInterface;
use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
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
     * @return TransactionRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(TransactionEntity::class);
    }
}
