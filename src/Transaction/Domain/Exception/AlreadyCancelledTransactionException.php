<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Exception;

class AlreadyCancelledTransactionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '이미 취소된 결제입니다.')
    {
        parent::__construct($message);
    }
}
