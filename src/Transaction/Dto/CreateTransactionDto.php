<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Dto;

class CreateTransactionDto
{
    /** @var string */
    public $transaction_id;

    /** @var string */
    public $partner_transaction_id;

    /** @var string */
    public $return_url;

    /**
     * @param string $transaction_id
     * @param string $partner_transaction_id
     * @param string $return_url
     */
    public function __construct(string $transaction_id, string $partner_transaction_id, string $return_url)
    {
        $this->transaction_id = $transaction_id;
        $this->partner_transaction_id = $partner_transaction_id;
        $this->return_url = $return_url;
    }
}
