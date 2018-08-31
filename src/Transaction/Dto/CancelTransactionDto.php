<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Dto;

use RidiPay\Transaction\Entity\TransactionEntity;

class CancelTransactionDto implements \JsonSerializable
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
        $this->approved_at = $transaction->getApprovedAt();
        $this->canceled_at = $transaction->getCanceledAt();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'transaction_id' => $this->transaction_id,
            'parnter_transaction_id' => $this->partner_transaction_id,
            'product_name' => $this->product_name,
            'amount' => $this->amount,
            'approved_at' => $this->approved_at->format(DATE_ATOM),
            'canceled_at' => $this->canceled_at->format(DATE_ATOM)
        ];
    }
}
