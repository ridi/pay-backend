<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use RidiPay\Transaction\Domain\Entity\TransactionEntity;

class TransactionCancellationResult
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
     * @param TransactionEntity $transaction
     */
    public function __construct(TransactionEntity $transaction)
    {
        $this->transaction_id = $transaction->getUuid()->toString();
        $this->partner_transaction_id = $transaction->getPartnerTransactionId();
        $this->product_name = $transaction->getProductName();
        $this->amount = $transaction->getAmount();
        $this->reserved_at = $transaction->getReservedAt();
        $this->approved_at = $transaction->getApprovedAt();
        $this->canceled_at = $transaction->getCanceledAt();
    }
}
