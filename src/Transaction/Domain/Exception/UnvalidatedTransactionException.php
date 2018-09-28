<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Exception;

class UnvalidatedTransactionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '인증이 완료되지 않은 결제 요청입니다.')
    {
        parent::__construct($message);
    }
}
