<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\User\Domain\Entity\CardEntity;

class CardRepository extends BaseEntityRepository
{
    /**
     * @return CardRepository
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getRepository(): self
    {
        return new self(CardEntity::class);
    }
}
