<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query\Expr;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ramsey\Uuid\UuidInterface;
use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Pg\Application\Dto\PgDto;
use RidiPay\Pg\Application\Service\PgAppService;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use Doctrine\DBAL\Types\Type;
use RidiPay\User\Domain\PaymentMethodConstant;

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
    public function findCardsByUidx(int $u_idx): array
    {
        $qb = $this->createQueryBuilder('pm')
            ->addSelect('c')
            ->join('pm.cards', 'c')
            ->where('pm.u_idx = :u_idx')
            ->andWhere('pm.type = :card_type')
            ->setParameter('u_idx', $u_idx, Type::INTEGER)
            ->setParameter('card_type', PaymentMethodConstant::TYPE_CARD, Type::STRING);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int $u_idx
     * @return PaymentMethodEntity[]
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function getAvailablePaymentMethods(int $u_idx)
    {
        $pgs = PgAppService::getPayablePgs();
        $pg_ids = array_map(
            function (PgDto $pg) {
                return $pg->id;
            },
            $pgs
        );

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
