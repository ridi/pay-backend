<?php
declare(strict_types=1);

namespace RidiPay\User\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Entity\UserActionHistoryEntity;

class UserActionHistoryRepository extends BaseEntityRepository
{
    /**
     * @return UserActionHistoryRepository
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(UserActionHistoryEntity::class);
    }
}
