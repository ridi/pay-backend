<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query\Expr;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\UuidInterface;
use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use Doctrine\DBAL\Types\Type;

class PaymentMethodRepository extends BaseEntityRepository
{
    /**
     * @param int $id
     * @return null|PaymentMethodEntity
     */
    public function findOneById(int $id): ?PaymentMethodEntity
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @param UuidInterface $uuid
     * @return null|PaymentMethodEntity
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByUuid(UuidInterface $uuid): ?PaymentMethodEntity
    {
        $qb = $this->createQueryBuilder('pm')
            ->addSelect('c')
            ->leftJoin('pm.cards', 'c')
            ->where('pm.uuid = :uuid')
            ->setParameter('uuid', $uuid, UuidBinaryType::NAME);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @param int $u_idx
     * @param int[] $pg_ids
     * @return PaymentMethodEntity[]
     */
    public function getAvailablePaymentMethods(int $u_idx, array $pg_ids)
    {
        $qb = $this->createQueryBuilder('pm')
            ->addSelect('c')
            ->leftJoin('pm.cards', 'c', Expr\Join::WITH, 'c.pg_id IN (:pg_ids)')
            ->setParameter('pg_ids', $pg_ids, Connection::PARAM_INT_ARRAY)
            ->join('c.card_issuer', 'ci')
            ->where('pm.u_idx = :u_idx')
            ->andWhere('pm.deleted_at IS NULL')
            ->setParameter('u_idx', $u_idx, Type::INTEGER);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return PaymentMethodRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(PaymentMethodEntity::class);
    }
}
