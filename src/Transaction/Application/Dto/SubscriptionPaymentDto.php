<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Service\BillingPaymentTransactionApprovalResult;

class SubscriptionPaymentDto
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
     * @param BillingPaymentTransactionApprovalResult $billing_payment_transaction_approval_result
     */
    public function __construct(BillingPaymentTransactionApprovalResult $billing_payment_transaction_approval_result)
    {
        $this->subscription_id = $billing_payment_transaction_approval_result->subscription_id;
        $this->transaction_id = $billing_payment_transaction_approval_result->transaction_id;
        $this->partner_transaction_id = $billing_payment_transaction_approval_result->partner_transaction_id;
        $this->product_name = $billing_payment_transaction_approval_result->product_name;
        $this->amount = $billing_payment_transaction_approval_result->amount;
        $this->subscribed_at = $billing_payment_transaction_approval_result->subscribed_at;
        $this->approved_at = $billing_payment_transaction_approval_result->approved_at;
    }
}
