<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Exception;

class NotFoundTransactionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '결제 내역을 찾을 수 없습니다.')
    {
        parent::__construct($message);
    }
}
