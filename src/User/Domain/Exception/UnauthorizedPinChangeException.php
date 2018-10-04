<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class UnauthorizedPinChangeException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '결제 비밀번호를 변경할 수 없습니다.')
    {
        parent::__construct($message);
    }
}
