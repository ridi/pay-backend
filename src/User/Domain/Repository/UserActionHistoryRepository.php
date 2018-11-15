<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Domain\Entity\UserActionHistoryEntity;

class UserActionHistoryRepository extends BaseEntityRepository
{
    /**
     * @param int $u_idx
     * @param array $actions
     * @return UserActionHistoryEntity[]
     */
    public function findByUidxAndActions(int $u_idx, array $actions): array
    {
        $qb = $this->createQueryBuilder('uah')
            ->join('uah.user', 'u')
            ->where('u.u_idx = :u_idx')
            ->andWhere('uah.action IN (:actions)')
            ->setParameter('u_idx', $u_idx, Type::INTEGER)
            ->setParameter('actions', $actions, Connection::PARAM_STR_ARRAY)
            ->orderBy('uah.id', Criteria::DESC);

        return $qb->getQuery()->getResult();
    }

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
