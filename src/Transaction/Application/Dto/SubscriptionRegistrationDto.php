<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;

class SubscriptionRegistrationDto
{
    /** @var string */
    public $subscription_id;

    /** @var string */
    public $return_url;

    /**
     * @param SubscriptionEntity $subscription
     * @param string $return_url
     */
    public function __construct(SubscriptionEntity $subscription, string $return_url)
    {
        $this->subscription_id = $subscription->getUuid()->toString();
        $this->return_url = $return_url;
    }
}
