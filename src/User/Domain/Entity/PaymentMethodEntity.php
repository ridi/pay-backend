<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;
use Doctrine\ORM\Mapping\Table;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @Table(
 *   name="payment_method",
 *   uniqueConstraints={
 *     @UniqueConstraint(name="uniq_uuid", columns={"uuid"})
 *   },
 *   indexes={
 *     @Index(name="idx_u_idx", columns={"u_idx"})
 *   }
 * )
 * @Entity(repositoryClass="RidiPay\User\Domain\Repository\PaymentMethodRepository")
 * @InheritanceType("JOINED")
 * @DiscriminatorColumn(name="type", type="string", columnDefinition="ENUM('CARD')")
 * @DiscriminatorMap({"CARD" = "CardEntity"})
 */
abstract class PaymentMethodEntity
{
    /**
     * @var int
     *
     * @Column(name="id", type="integer", nullable=false, options={"unsigned"=true})
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    protected $id;

    /**
     * @var UuidInterface
     *
     * @Column(name="uuid", type="uuid_binary", nullable=false, options={"comment"="id 값 유추 방지를 위한 uuid"})
     */
    protected $uuid;

    /**
     * @var int
     *
     * @Column(name="u_idx", type="integer", nullable=false, options={"comment"="user.u_idx"})
     */
    protected $u_idx;

    /**
     * @var \DateTime
     *
     * @Column(
     *   name="created_at",
     *   type="datetime",
     *   nullable=false,
     *   options={
     *     "default"="CURRENT_TIMESTAMP",
     *     "comment"="결제 수단 등록 시각"
     *   }
     * )
     */
    protected $created_at;

    /**
     * @var \DateTime|null
     *
     * @Column(name="deleted_at", type="datetime", nullable=true, options={"comment"="결제 수단 삭제 시각"})
     */
    protected $deleted_at;

    /**
     * @param int $u_idx
     * @throws \Exception
     */
    protected function __construct(int $u_idx)
    {
        $this->uuid = Uuid::uuid4();
        $this->u_idx = $u_idx;
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
     * @return int
     */
    public function getUidx(): int
    {
        return $this->u_idx;
    }

    /**
     * @return string
     */
    abstract public function getType(): string;

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->created_at;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return !is_null($this->deleted_at);
    }

    /**
     * @return \DateTime
     */
    public function getDeletedAt(): \DateTime
    {
        return $this->deleted_at;
    }

    /**
     * SOFT DELETE
     */
    public function delete(): void
    {
        $this->deleted_at = new \DateTime();
    }
}
