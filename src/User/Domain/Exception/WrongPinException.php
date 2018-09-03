<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class WrongPinException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '결제 비밀번호는 0 ~ 9 사이의 6자리 숫자로 입력해야합니다.')
    {
        parent::__construct($message);
    }
}
