<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\User\Domain\Entity\CardEntity;

class CardRepository extends BaseEntityRepository
{
    /**
     * @param int $u_idx
     * @return CardEntity[]
     */
    public function findByUidx(int $u_idx): array
    {
        return $this->findBy(['u_idx' => $u_idx]);
    }

    /**
     * @return static
     */
    public static function getRepository(): self
    {
        return new self(CardEntity::class);
    }
}
