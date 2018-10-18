<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Entity;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @Table(
 *   name="subscription",
 *   indexes={
 *     @Index(name="idx_payment_method_id", columns={"payment_method_id"}),
 *     @Index(name="idx_partner_id", columns={"partner_id"})
 *   }
 * )
 * @Entity(repositoryClass="RidiPay\Transaction\Domain\Repository\SubscriptionRepository")
 */
class SubscriptionEntity
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
     * @var int
     *
     * @Column(
     *   name="payment_method_id",
     *   type="integer",
     *   nullable=false,
     *   options={
     *     "unsigned"=true,
     *     "comment"="payment_method.id"
     *   }
     * )
     */
    private $payment_method_id;

    /**
     * @var int
     *
     * @Column(name="partner_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="partner.id"})
     */
    private $partner_id;

    /**
     * @var UuidInterface
     *
     * @Column(name="bill_key", type="uuid_binary", nullable=false, options={"comment"="정기 결제 Bill Key"})
     */
    private $bill_key;

    /**
     * @var string
     *
     * @Column(name="product_name", type="string", length=32, nullable=false, options={"comment"="구독 상품명"})
     */
    private $product_name;

    /**
     * @var int
     *
     * @Column(name="amount", type="integer", nullable=false, options={"comment"="주문 금액"})
     */
    private $amount;

    /**
     * @var \DateTime
     *
     * @Column(name="subscribed_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $subscribed_at;

    /**
     * @var \DateTime|null
     *
     * @Column(name="unsubscribed_at", type="datetime", nullable=true)
     */
    private $unsubscribed_at;

    /**
     * @param int $payment_method_id
     * @param int $partner_id
     * @param string $product_name
     * @param int $amount
     * @throws \Exception
     */
    public function __construct(
        int $payment_method_id,
        int $partner_id,
        string $product_name,
        int $amount
    ) {
        $this->payment_method_id = $payment_method_id;
        $this->partner_id = $partner_id;
        $this->bill_key = Uuid::uuid4();
        $this->product_name = $product_name;
        $this->amount = $amount;
        $this->subscribed_at = new \DateTime();
        $this->unsubscribed_at = null;
    }

    /**
     * @return int
     */
    public function getPaymentMethodId(): int
    {
        return $this->payment_method_id;
    }

    /**
     * @return UuidInterface
     */
    public function getBillKey(): UuidInterface
    {
        return $this->bill_key;
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
     * @return \DateTime
     */
    public function getSubscribedAt(): \DateTime
    {
        return $this->subscribed_at;
    }
}
