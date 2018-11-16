<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Exception;

class AlreadyRegisteredSubscriptionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '이미 등록된 구독입니다.')
    {
        parent::__construct($message);
    }
}
