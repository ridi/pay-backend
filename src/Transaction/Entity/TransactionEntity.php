<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Entity;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RidiPay\Transaction\Constant\TransactionConstant;

/**
 * @Table(name="transaction", indexes={@Index(name="idx_payment_method_id", columns={"payment_method_id"}), @Index(name="idx_partner_id", columns={"partner_id"}), @Index(name="idx_pg_id", columns={"pg_id"}), @Index(name="idx_u_idx", columns={"u_idx"})})
 * @Entity(repositoryClass="RidiPay\Transaction\Repository\TransactionRepository")
 */
class TransactionEntity
{
    /**
     * @var int
     *
     * @Column(name="id", type="bigint", nullable=false, options={"unsigned"=true})
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
     * @var int
     *
     * @Column(name="payment_method_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="payment_method.id"})
     */
    private $payment_method_id;

    /**
     * @var int
     *
     * @Column(name="partner_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="partner.id"})
     */
    private $partner_id;

    /**
     * @var int|null
     *
     * @Column(name="pg_id", type="integer", nullable=true, options={"unsigned"=true, "comment"="pg.id"})
     */
    private $pg_id;

    /**
     * @var string
     *
     * @Column(name="partner_transaction_id", type="string", length=64, nullable=false, options={"comment"="Partner 주문 번호"})
     */
    private $partner_transaction_id;

    /**
     * @var string|null
     *
     * @Column(name="pg_transaction_id", type="string", length=64, nullable=true, options={"comment"="PG사 t_id"})
     */
    private $pg_transaction_id;

    /**
     * @var string
     *
     * @Column(name="product_name", type="string", length=32, nullable=false, options={"comment"="상품명"})
     */
    private $product_name;

    /**
     * @var int
     *
     * @Column(name="amount", type="integer", nullable=false, options={"comment"="주문 금액"})
     */
    private $amount;

    /**
     * @var string
     *
     * @Column(name="status", type="string", length=0, nullable=false, columnDefinition="ENUM('RESERVED','APPROVED','CANCELED')", options={"default"="RESERVED","comment"="Transaction 상태"})
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP","comment"="Transaction 생성 시각"})
     */
    private $created_at;

    /**
     * @var \DateTime|null
     *
     * @Column(name="approved_at", type="datetime", nullable=true, options={"comment"="Transaction 승인 시각"})
     */
    private $approved_at;

    /**
     * @var \DateTime|null
     *
     * @Column(name="canceled_at", type="datetime", nullable=true, options={"comment"="Transaction 취소 시각"})
     */
    private $canceled_at;

    /**
     * @param int $u_idx
     * @param int $payment_method_id
     * @param int $pg_id
     * @param int $partner_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @throws \Exception
     */
    public function __construct(
        int $u_idx,
        int $payment_method_id,
        int $pg_id,
        int $partner_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount
    ) {
        $this->uuid = Uuid::uuid4();
        $this->u_idx = $u_idx;
        $this->payment_method_id = $payment_method_id;
        $this->pg_id = $pg_id;
        $this->partner_id = $partner_id;
        $this->partner_transaction_id = $partner_transaction_id;
        $this->pg_transaction_id = null;
        $this->product_name = $product_name;
        $this->amount = $amount;
        $this->status = TransactionConstant::STATUS_RESERVED;
        $this->created_at = new \DateTime();
        $this->approved_at = null;
        $this->canceled_at = null;
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
     * @return int
     */
    public function getPaymentMethodId(): int
    {
        return $this->payment_method_id;
    }

    /**
     * @return int
     */
    public function getPgId(): int
    {
        return $this->pg_id;
    }

    /**
     * @return string
     */
    public function getPartnerTransactionId(): string
    {
        return $this->partner_transaction_id;
    }

    /**
     * @return string
     */
    public function getPgTransactionId(): string
    {
        return $this->pg_transaction_id;
    }

    /**
     * @return string
     */
    public function getProductName(): string
    {
        return $this->product_name;
    }

    /**
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @return bool
     */
    public function isApproved(): bool
    {
        return $this->status === TransactionConstant::STATUS_APPROVED;
    }

    /**
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return \DateTime|null
     */
    public function getApprovedAt(): ?\DateTime
    {
        return $this->approved_at;
    }

    /**
     * @return \DateTime|null
     */
    public function getCanceledAt(): ?\DateTime
    {
        return $this->canceled_at;
    }

    /**
     * @param string $pg_transaction_id
     */
    public function approve(string $pg_transaction_id): void
    {
        $this->pg_transaction_id = $pg_transaction_id;
        $this->status = TransactionConstant::STATUS_APPROVED;
        $this->approved_at = new \DateTime();
    }

    public function cancel(): void
    {
        $this->status = TransactionConstant::STATUS_CANCELED;
        $this->canceled_at = new \DateTime();
    }
}
