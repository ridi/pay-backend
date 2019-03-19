<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

class ApproveTransactionDto implements \JsonSerializable
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
     * @param string $transaction_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @param \DateTime $reserved_at
     * @param \DateTime $approved_at
     */
    public function __construct(
        string $transaction_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount,
        \DateTime $reserved_at,
        \DateTime $approved_at
    ){
        $this->transaction_id = $transaction_id;
        $this->partner_transaction_id = $partner_transaction_id;
        $this->product_name = $product_name;
        $this->amount = $amount;
        $this->reserved_at = $reserved_at;
        $this->approved_at = $approved_at;
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
