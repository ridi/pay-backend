<?php
declare(strict_types=1);

namespace RidiPay\User\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\User\Entity\CardEntity;

class CardRepository extends BaseEntityRepository
{
    /**
     * @return CardRepository
     */
    public static function getRepository()
    {
        return EntityManagerProvider::getEntityManager()->getRepository(CardEntity::class);
    }
}
