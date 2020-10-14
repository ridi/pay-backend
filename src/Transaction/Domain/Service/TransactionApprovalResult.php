<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use RidiPay\Transaction\Domain\Entity\TransactionEntity;

class TransactionApprovalResult implements \JsonSerializable
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
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'transaction_id' => $this->transaction_id,
            'partner_transaction_id' => $this->partner_transaction_id,
            'product_name' => $this->product_name,
            'amount' => $this->amount,
            'reserved_at' => $this->reserved_at->format(DATE_ATOM),
            'approved_at' => $this->approved_at->format(DATE_ATOM)
        ];
    }
}
