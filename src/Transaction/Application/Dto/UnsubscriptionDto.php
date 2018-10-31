<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;

class UnsubscriptionDto
{
    /** @var string */
    public $subscription_id;

    /** @var string */
    public $product_name;

    /** @var int */
    public $amount;

    /** @var \DateTime */
    public $subscribed_at;

    /** @var \DateTime */
    public $unsubscribed_at;

    /**
     * @param SubscriptionEntity $subscription
     */
    public function __construct(SubscriptionEntity $subscription)
    {
        $this->subscription_id = $subscription->getUuid()->toString();
        $this->product_name = $subscription->getProductName();
        $this->amount = $subscription->getAmount();
        $this->subscribed_at = $subscription->getSubscribedAt();
        $this->unsubscribed_at = $subscription->getUnsubscribedAt();
    }
}
