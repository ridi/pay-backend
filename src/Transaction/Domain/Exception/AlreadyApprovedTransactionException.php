<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Exception;

class AlreadyApprovedTransactionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '이미 승인된 결제입니다.')
    {
        parent::__construct($message);
    }
}
