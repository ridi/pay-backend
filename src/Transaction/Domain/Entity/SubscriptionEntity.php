<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Entity;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledSubscriptionException;
use RidiPay\Transaction\Domain\Exception\AlreadyResumedSubscriptionException;

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
     * @var UuidInterface
     *
     * @Column(name="uuid", type="uuid_binary", nullable=false, options={"comment"="PG사 bill key와 1:1 대응"})
     */
    private $uuid;

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
     * @var string
     *
     * @Column(name="product_name", type="string", length=32, nullable=false, options={"comment"="구독 상품명"})
     */
    private $product_name;

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
     * @throws \Exception
     */
    public function __construct(int $payment_method_id, int $partner_id, string $product_name)
    {
        $this->payment_method_id = $payment_method_id;
        $this->partner_id = $partner_id;
        $this->uuid = Uuid::uuid4();
        $this->product_name = $product_name;
        $this->subscribed_at = new \DateTime();
        $this->unsubscribed_at = null;
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
    public function getPaymentMethodId(): int
    {
        return $this->payment_method_id;
    }

    /**
     * @return int
     */
    public function getPartnerId(): int
    {
        return $this->partner_id;
    }

    /**
     * @return string
     */
    public function getProductName(): string
    {
        return $this->product_name;
    }

    /**
     * @return \DateTime
     */
    public function getSubscribedAt(): \DateTime
    {
        return $this->subscribed_at;
    }

    /**
     * @return bool
     */
    public function isUnsubscribed(): bool
    {
        return !is_null($this->unsubscribed_at);
    }

    /**
     * @return \DateTime|null
     */
    public function getUnsubscribedAt(): ?\DateTime
    {
        return $this->unsubscribed_at;
    }

    /**
     * @throws AlreadyCancelledSubscriptionException
     */
    public function unsubscribe(): void
    {
        if (!is_null($this->unsubscribed_at)) {
            throw new AlreadyCancelledSubscriptionException();
        }

        $this->unsubscribed_at = new \DateTime();
    }

    /**
     * @throws AlreadyResumedSubscriptionException
     */
    public function resumeSubscription(): void
    {
        if (is_null($this->unsubscribed_at)) {
            throw new AlreadyResumedSubscriptionException();
        }

        $this->unsubscribed_at = null;
    }

    /**
     * @param int $payment_method_id
     */
    public function setPaymentMethodId(int $payment_method_id): void
    {
        $this->payment_method_id = $payment_method_id;
    }
}
