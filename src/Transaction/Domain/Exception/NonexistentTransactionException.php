<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Exception;

class NonexistentTransactionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '존재하지 않는 결제 내역에 대한 요청입니다.')
    {
        parent::__construct($message);
    }
}
