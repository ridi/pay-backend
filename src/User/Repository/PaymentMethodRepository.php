<?php
declare(strict_types=1);

namespace RidiPay\User\Repository;

use Doctrine\ORM\Query\Expr;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\UuidInterface;
use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Entity\PaymentMethodEntity;
use RidiPay\Transaction\Constant\PgConstant;
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
     * @return PaymentMethodEntity[]
     */
    public function getAvailablePaymentMethods(int $u_idx)
    {
        $qb = $this->createQueryBuilder('pm')
            ->addSelect('c')
            ->join('pm.user', 'u')
            ->leftJoin('pm.cards', 'c')
            ->join('c.card_issuer', 'ci')
            ->join('c.pg', 'pg', Expr\Join::WITH, 'pg.status != :inactive')
            ->where('u.u_idx = :u_idx')
            ->andWhere('pm.deleted_at IS NULL')
            ->setParameter('inactive', PgConstant::STATUS_INACTIVE, Type::STRING)
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
