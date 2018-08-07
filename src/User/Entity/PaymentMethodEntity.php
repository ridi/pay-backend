<?php

namespace RidiPay\User\Entity;

use RidiPay\User\Constant\PaymentMethodTypeConstant;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @Table(name="payment_method", uniqueConstraints={@UniqueConstraint(name="uniq_uuid", columns={"uuid"})}, indexes={@Index(name="idx_u_idx", columns={"u_idx"})})
 * @Entity(repositoryClass="RidiPay\User\Repository\PaymentMethodRepository")
 */
class PaymentMethodEntity
{
    /**
     * @var int
     *
     * @Column(name="id", type="integer", nullable=false, options={"unsigned"=true})
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var UuidInterface
     *
     * @Column(name="uuid", type="uuid_binary", nullable=false, options={"comment"="id 값 유추 방지를 위한 uuid"})
     */
    private $uuid;

    /**
     * @var int
     *
     * @Column(name="u_idx", type="integer", nullable=false, options={"comment"="user.u_idx"})
     */
    private $u_idx;

    /**
     * @var string
     *
     * @Column(name="type", type="string", length=0, nullable=false, columnDefinition="ENUM('CARD')", options={"default"="CARD","comment"="결제 수단"})
     */
    private $type;

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP","comment"="결제 수단 등록 시각"})
     */
    private $created_at;

    /**
     * @var \DateTime|null
     *
     * @Column(name="deleted_at", type="datetime", nullable=true, options={"comment"="결제 수단 삭제 시각"})
     */
    private $deleted_at;

    /**
     * @var CardEntity[]
     *
     * @OneToMany(targetEntity="CardEntity", mappedBy="payment_method")
     */
    private $cards;

    /**
     * @param int $u_idx
     * @param string $type
     */
    public function __construct(int $u_idx, string $type)
    {
        $this->uuid = Uuid::uuid4();
        $this->u_idx = $u_idx;
        $this->type = $type;
        $this->created_at = new \DateTime();
        $this->deleted_at = null;
    }

    /**
     * @return UuidInterface
     */
    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @return bool
     */
    public function isCard(): bool
    {
        return $this->type === PaymentMethodTypeConstant::CARD;
    }

    /**
     * SOFT DELETE
     */
    public function delete(): void
    {
        $this->deleted_at = new \DateTime();
    }

    /**
     * @return CardEntity[]
     */
    public function getCards(): array
    {
        if (!$this->isCard()) {
            return [];
        }

        return $this->cards;
    }

    /**
     * @return null|CardEntity
     */
    public function getCardForOneTimePayment(): ?CardEntity
    {
        if (!$this->isCard()) {
            return null;
        }

        foreach ($this->cards as $card) {
            if ($card->isAvailableOnOneTimePayment()) {
                return $card;
            }
        }

        return null;
    }

    /**
     * @return null|CardEntity
     */
    public function getCardForSubscriptionPayment(): ?CardEntity
    {
        if (!$this->isCard()) {
            return null;
        }

        foreach ($this->cards as $card) {
            if ($card->isAvailableOnSubscriptionPayment()) {
                return $card;
            }
        }

        return null;
    }
}
