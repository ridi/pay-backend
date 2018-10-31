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
     * @param int $payment_method_id
     * @return SubscriptionEntity[]
     */
    public function findByPaymentMethodId(int $payment_method_id): array
    {
        return $this->findBy([
            'payment_method_id' => $payment_method_id,
            'unsubscribed_at' => null
        ]);
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
