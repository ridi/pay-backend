<?php
declare(strict_types=1);

namespace RidiPay\User\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Entity\PaymentMethodEntity;
use RidiPay\Transaction\Constant\PgConstant;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Query\Expr;
use Ramsey\Uuid\Uuid;

class PaymentMethodRepository extends BaseEntityRepository
{
    /**
     * @param string $uuid
     * @return null|PaymentMethodEntity
     */
    public function findOneByUuid(string $uuid): ?PaymentMethodEntity
    {
        return $this->findOneBy(['uuid' => Uuid::fromString($uuid)]);
    }

    /**
     * @param int $u_idx
     * @return PaymentMethodEntity[]
     */
    public function getPaymentMethods(int $u_idx)
    {
        $qb = $this->createQueryBuilder('pm')
            ->addSelect('c')
            ->leftJoin('pm.cards', 'c', Expr\Join::WITH)
            ->join('c.card_issuer', 'ci', Expr\Join::WITH)
            ->join('c.pg', 'pg', Expr\Join::WITH, 'pg.status != :inactive')
            ->where('pm.u_idx = :u_idx')
            ->andWhere('pm.deleted_at IS NULL')
            ->setParameter('inactive', PgConstant::STATUS_INACTIVE, Type::STRING)
            ->setParameter('u_idx', $u_idx, Type::INTEGER);

        return $qb->getQuery()->execute();
    }

    /**
     * @return PaymentMethodRepository
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(PaymentMethodEntity::class);
    }
}
