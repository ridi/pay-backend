<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Exception;

class AlreadyResumedSubscriptionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '이미 해지 취소된 구독입니다.')
    {
        parent::__construct($message);
    }
}
