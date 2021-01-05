<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Pg\Domain\PgConstant;
use RidiPay\Pg\Domain\Entity\PgEntity;

class PgRepository extends BaseEntityRepository
{
    /**
     * @param int $id
     * @return null|PgEntity
     */
    public function findOneById(int $id): ?PgEntity
    {
        return $this->findOneBy(['id' => $id]);
    }

    /**
     * @return null|PgEntity
     */
    public function findActiveOne(): ?PgEntity
    {
        return $this->findOneBy(['status' => PgConstant::STATUS_ACTIVE]);
    }

    /**
     * @return PgRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository(): self
    {
        return new self(PgEntity::class);
    }
}
