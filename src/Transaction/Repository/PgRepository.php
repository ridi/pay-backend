<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Entity\PgEntity;

class PgRepository extends BaseEntityRepository
{
    /**
     * @param string $name
     * @return null|\RidiPay\Transaction\Entity\PgEntity
     */
    public function findOneByName(string $name): ?PgEntity
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return PgRepository
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(PgEntity::class);
    }
}
