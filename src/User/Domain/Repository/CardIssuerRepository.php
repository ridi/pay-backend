<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\User\Domain\Entity\CardIssuerEntity;

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
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository(): self
    {
        return new self(CardIssuerEntity::class);
    }
}
