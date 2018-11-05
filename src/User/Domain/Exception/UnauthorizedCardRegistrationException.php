<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class UnauthorizedCardRegistrationException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '카드를 등록할 수 없습니다.')
    {
        parent::__construct($message);
    }
}
