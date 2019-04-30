<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Entity;

use Doctrine\ORM\Mapping\Column as Column;
use Doctrine\ORM\Mapping\Entity as Entity;
use Doctrine\ORM\Mapping\GeneratedValue as GeneratedValue;
use Doctrine\ORM\Mapping\Id as Id;
use Doctrine\ORM\Mapping\Index as Index;
use Doctrine\ORM\Mapping\JoinColumn as JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne as ManyToOne;
use Doctrine\ORM\Mapping\Table as Table;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;

/**
 * @Table(
 *   name="subscription_payment_method_history",
 *   indexes={
 *     @Index(name="idx_subscription_id", columns={"subscription_id"}),
 *     @Index(name="idx_payment_method_id", columns={"payment_method_id"})
 *   }
 * )
 * @Entity(repositoryClass="RidiPay\Transaction\Domain\Repository\SubscriptionPaymentMethodHistoryRepository")
 */
class SubscriptionPaymentMethodHistoryEntity
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
     * @var SubscriptionEntity
     *
     * @ManyToOne(targetEntity="RidiPay\Transaction\Domain\Entity\SubscriptionEntity")
     * @JoinColumn(name="subscription_id", referencedColumnName="id", nullable=false)
     */
    private $subscription;

    /**
     * @var int
     *
     * @ManyToOne(targetEntity="RidiPay\User\Domain\Entity\PaymentMethodEntity")
     * @JoinColumn(name="payment_method_id", referencedColumnName="id", nullable=false)
     */
    private $payment_method;

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created_at;

    /**
     * @param SubscriptionEntity $subscription
     * @param PaymentMethodEntity $payment_method
     * @throws \Exception
     */
    public function __construct(SubscriptionEntity $subscription, PaymentMethodEntity $payment_method)
    {
        $this->subscription = $subscription;
        $this->payment_method = $payment_method;
        $this->created_at = new \DateTime();
    }
}
