<?php

namespace RidiPay\User\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
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
     * @var UserEntity
     *
     * @ManyToOne(targetEntity="RidiPay\User\Entity\UserEntity")
     * @JoinColumn(name="u_idx", referencedColumnName="u_idx", nullable=false)
     */
    private $user;

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
     * @var PersistentCollection
     *
     * @OneToMany(targetEntity="RidiPay\User\Entity\CardEntity", mappedBy="payment_method")
     */
    private $cards;

    /**
     * @param UserEntity $user
     * @param string $type
     * @throws \Exception
     */
    public function __construct(UserEntity $user, string $type)
    {
        $this->uuid = Uuid::uuid4();
        $this->user = $user;
        $this->type = $type;
        $this->created_at = new \DateTime();
        $this->deleted_at = null;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
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
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
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

        return $this->cards->getValues();
    }

    /**
     * @return null|CardEntity
     */
    public function getCardForOneTimePayment(): ?CardEntity
    {
        foreach ($this->getCards() as $card) {
            if ($card->isAvailableOnOneTimePayment()) {
                return $card;
            }
        }

        return null;
    }

    /**
     * @return null|CardEntity
     */
    public function getCardForBillingPayment(): ?CardEntity
    {
        foreach ($this->getCards() as $card) {
            if ($card->isAvailableOnBillingPayment()) {
                return $card;
            }
        }

        return null;
    }
}
