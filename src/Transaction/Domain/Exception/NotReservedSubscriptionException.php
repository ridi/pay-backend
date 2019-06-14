<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Exception;

class NotReservedSubscriptionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '예약되지 않은 구독에 대한 요청입니다.')
    {
        parent::__construct($message);
    }
}
