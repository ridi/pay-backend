<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

class BillingPaymentTransactionApprovalResult implements \JsonSerializable
{
    /** @var string */
    public $subscription_id;

    /** @var string */
    public $transaction_id;

    /** @var string */
    public $partner_transaction_id;

    /** @var string */
    public $product_name;

    /** @var int */
    public $amount;

    /** @var \DateTime */
    public $subscribed_at;

    /** @var \DateTime */
    public $approved_at;

    /**
     * @param string $subscription_id
     * @param string $transaction_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @param \DateTime $subscribed_at
     * @param \DateTime $approved_at
     */
    public function __construct(
        string $subscription_id,
        string $transaction_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount,
        \DateTime $subscribed_at,
        \DateTime $approved_at
    ) {
        $this->subscription_id = $subscription_id;
        $this->transaction_id = $transaction_id;
        $this->partner_transaction_id = $partner_transaction_id;
        $this->product_name = $product_name;
        $this->amount = $amount;
        $this->subscribed_at = $subscribed_at;
        $this->approved_at = $approved_at;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'subscription_id' => $this->subscription_id,
            'transaction_id' => $this->transaction_id,
            'partner_transaction_id' => $this->partner_transaction_id,
            'product_name' => $this->product_name,
            'amount' => $this->amount,
            'subscribed_at' => $this->subscribed_at->format(DATE_ATOM),
            'approved_at' => $this->approved_at->format(DATE_ATOM)
        ];
    }
}
