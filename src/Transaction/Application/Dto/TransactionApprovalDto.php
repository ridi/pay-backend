<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Service\OneTimePaymentTransactionApprovalResult;

class TransactionApprovalDto
{
    /** @var string */
    public $transaction_id;

    /** @var string */
    public $partner_transaction_id;

    /** @var string */
    public $product_name;

    /** @var int */
    public $amount;

    /** @var \DateTime */
    public $reserved_at;

    /** @var \DateTime */
    public $approved_at;

    /**
     * @param OneTimePaymentTransactionApprovalResult $one_time_payment_transaction_approval_result
     */
    public function __construct(OneTimePaymentTransactionApprovalResult $one_time_payment_transaction_approval_result)
    {
        $this->transaction_id = $one_time_payment_transaction_approval_result->transaction_id;
        $this->partner_transaction_id = $one_time_payment_transaction_approval_result->partner_transaction_id;
        $this->product_name = $one_time_payment_transaction_approval_result->product_name;
        $this->amount = $one_time_payment_transaction_approval_result->amount;
        $this->reserved_at = $one_time_payment_transaction_approval_result->reserved_at;
        $this->approved_at = $one_time_payment_transaction_approval_result->approved_at;
    }
}
