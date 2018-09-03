<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Domain\Entity\UserActionHistoryEntity;

class UserActionHistoryRepository extends BaseEntityRepository
{
    /**
     * @return UserActionHistoryRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(UserActionHistoryEntity::class);
    }
}
