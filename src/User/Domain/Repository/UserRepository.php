<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Domain\Entity\UserEntity;

class UserRepository extends BaseEntityRepository
{
    /**
     * @param int $u_idx
     * @return UserEntity|null
     */
    public function findOneByUidx(int $u_idx): ?UserEntity
    {
        return $this->findOneBy(['u_idx' => $u_idx]);
    }

    /**
     * @return UserRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(UserEntity::class);
    }
}
