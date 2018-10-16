<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;

class SubscriptionPaymentDto
{
    /** @var string */
    public $transaction_id;

    /** @var string */
    public $partner_transaction_id;

    /** @var string */
    public $subscription_id;

    /** @var string */
    public $product_name;

    /** @var int */
    public $amount;

    /** @var \DateTime */
    public $subscribed_at;

    /** @var \DateTime */
    public $approved_at;

    /**
     * @param ApproveTransactionDto $approve_transaction_dto
     * @param SubscriptionEntity $subscription
     */
    public function __construct(
        ApproveTransactionDto $approve_transaction_dto,
        SubscriptionEntity $subscription
    ) {
        $this->transaction_id = $approve_transaction_dto->transaction_id;
        $this->partner_transaction_id = $approve_transaction_dto->partner_transaction_id;
        $this->subscription_id = $subscription->getBillKey();
        $this->product_name = $subscription->getProductName();
        $this->amount = $subscription->getAmount();
        $this->subscribed_at = $subscription->getSubscribedAt();
        $this->approved_at = $approve_transaction_dto->approved_at;
    }
}
