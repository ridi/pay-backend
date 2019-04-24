<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Service\TransactionCancellationResult;

class TransactionCancellationDto
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

    /** @var \DateTime */
    public $canceled_at;

    /**
     * @param TransactionCancellationResult $transaction_cancellation_result
     */
    public function __construct(TransactionCancellationResult $transaction_cancellation_result)
    {
        $this->transaction_id = $transaction_cancellation_result->transaction_id;
        $this->partner_transaction_id = $transaction_cancellation_result->partner_transaction_id;
        $this->product_name = $transaction_cancellation_result->product_name;
        $this->amount = $transaction_cancellation_result->amount;
        $this->reserved_at = $transaction_cancellation_result->reserved_at;
        $this->approved_at = $transaction_cancellation_result->approved_at;
        $this->canceled_at = $transaction_cancellation_result->canceled_at;
    }
}
