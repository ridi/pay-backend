<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;

class SubscriptionDto
{
    /** @var string */
    public $subscription_id;

    /** @var string */
    public $product_name;

    /** @var \DateTime */
    public $subscribed_at;

    /**
     * @param SubscriptionEntity $subscription
     */
    public function __construct(SubscriptionEntity $subscription)
    {
        $this->subscription_id = $subscription->getUuid()->toString();
        $this->product_name = $subscription->getProductName();
        $this->subscribed_at = $subscription->getSubscribedAt();
    }
}
