<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Repository;

use RidiPay\Library\BaseEntityRepository;
use RidiPay\User\Domain\Entity\CardPaymentKeyEntity;

class CardPaymentKeyRepository extends BaseEntityRepository
{
    /**
     * @param int $card_id
     * @param string $purpose
     * @return CardPaymentKeyEntity|null
     */
    public function findOneByCardIdAndPurpose(int $card_id, string $purpose): ?CardPaymentKeyEntity
    {
        return $this->findOneBy(['card' => $card_id, 'purpose' => $purpose]);
    }

    /**
     * @return CardPaymentKeyRepository
     */
    public static function getRepository(): self
    {
        return new self(CardPaymentKeyEntity::class);
    }
}
