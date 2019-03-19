<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Exception;

class AlreadyRunningTransactionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '이미 진행 중인 결제입니다.')
    {
        parent::__construct($message);
    }
}
