<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;

class BillingPaymentTransactionApprovalResult extends TransactionApprovalResult
{
    /** @var string */
    public $subscription_id;

    /** @var \DateTime */
    public $subscribed_at;

    /**
     * @param SubscriptionEntity $subscription
     * @param TransactionEntity $transaction
     */
    public function __construct(SubscriptionEntity $subscription, TransactionEntity $transaction)
    {
        parent::__construct($transaction);

        $this->subscription_id = $subscription->getUuid()->toString();
        $this->subscribed_at = $subscription->getSubscribedAt();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'subscription_id' => $this->subscription_id,
                'subscribed_at' => $this->subscribed_at->format(DATE_ATOM),
            ]
        );
    }
}
