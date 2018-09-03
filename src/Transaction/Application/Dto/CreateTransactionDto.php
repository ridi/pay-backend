<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

class CreateTransactionDto
{
    /** @var string */
    public $transaction_id;

    /** @var string */
    public $return_url;

    /**
     * @param string $transaction_id
     * @param string $return_url
     */
    public function __construct(string $transaction_id, string $return_url)
    {
        $this->transaction_id = $transaction_id;
        $this->return_url = $return_url;
    }
}
