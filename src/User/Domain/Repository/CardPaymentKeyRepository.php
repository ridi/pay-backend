<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\User\Domain\Entity\CardPaymentKeyEntity;

class CardPaymentKeyRepository extends BaseEntityRepository
{
    /**
     * @return CardPaymentKeyRepository
     */
    public static function getRepository(): self
    {
        return new self(CardPaymentKeyEntity::class);
    }
}
