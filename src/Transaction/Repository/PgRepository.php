<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Constant\PgConstant;
use RidiPay\Transaction\Entity\PgEntity;

class PgRepository extends BaseEntityRepository
{
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
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(PgEntity::class);
    }
}
