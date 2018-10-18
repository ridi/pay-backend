<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;

class SubscriptionDto
{
    /** @var string */
    public $subscription_id;

    /** @var string */
    public $payment_method_id;

    /** @var string */
    public $product_name;

    /** @var int */
    public $amount;

    /** @var \DateTime */
    public $subscribed_at;

    /**
     * @param string $payment_method_id
     * @param SubscriptionEntity $subscription
     */
    public function __construct(string $payment_method_id, SubscriptionEntity $subscription)
    {
        $this->subscription_id = $subscription->getUuid()->toString();
        $this->payment_method_id = $payment_method_id;
        $this->product_name = $subscription->getProductName();
        $this->amount = $subscription->getAmount();
        $this->subscribed_at = $subscription->getSubscribedAt();
    }
}
