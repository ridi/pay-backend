<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Repository;

use Ramsey\Uuid\UuidInterface;
use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;

class SubscriptionRepository extends BaseEntityRepository
{
    /**
     * @param UuidInterface $uuid
     * @return null|SubscriptionEntity
     */
    public function findOneByUuid(UuidInterface $uuid): ?SubscriptionEntity
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }

    /**
     * @return SubscriptionRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(SubscriptionEntity::class);
    }
}
