<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Dto;

use RidiPay\Transaction\Entity\TransactionEntity;

class TransactionStatusDto implements \JsonSerializable
{
    /** @var string */
    public $transaction_id;

    /** @var string */
    public $partner_transaction_id;

    /** @var string */
    public $status;

    /** @var string */
    public $product_name;

    /** @var int */
    public $amount;

    /** @var \DateTime|null */
    public $approved_at;

    /** @var \DateTime|null */
    public $canceled_at;

    /**
     * @param TransactionEntity $transaction
     */
    public function __construct(TransactionEntity $transaction)
    {
        $this->transaction_id = $transaction->getUuid()->toString();
        $this->partner_transaction_id = $transaction->getPartnerTransactionId();
        $this->status = $transaction->getStatus();
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
        $data = [
            'transaction_id' => $this->transaction_id,
            'partner_transaction_id' => $this->partner_transaction_id,
            'status' => $this->status,
            'product_name' => $this->product_name,
            'amount' => $this->amount
        ];

        if (!is_null($this->approved_at)) {
            $data['approved_at'] = $this->approved_at;
        }
        if (!is_null($this->canceled_at)) {
            $data['canceled_at'] = $this->canceled_at;
        }
    }
}
