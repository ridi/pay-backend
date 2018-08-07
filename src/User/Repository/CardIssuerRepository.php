<?php
declare(strict_types=1);

namespace RidiPay\User\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Entity\CardIssuerEntity;

class CardIssuerRepository extends BaseEntityRepository
{
    /**
     * @param int $pg_id
     * @param string $code
     * @return CardIssuerEntity|null
     */
    public function findOneByPgIdAndCode(int $pg_id, string $code): ?CardIssuerEntity
    {
        return $this->findOneBy(['pg_id' => $pg_id, 'code' => $code]);
    }

    /**
     * @return CardIssuerRepository
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(CardIssuerEntity::class);
    }
}
