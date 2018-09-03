<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Exception;

class NonTransactionOwnerException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '올바르지 않은 결제 요청입니다.')
    {
        parent::__construct($message);
    }
}
