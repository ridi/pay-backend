<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Exception;

class NotFoundSubscriptionException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '구독 내역을 찾을 수 없습니다.')
    {
        parent::__construct($message);
    }
}
