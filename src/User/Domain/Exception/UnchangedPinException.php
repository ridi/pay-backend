<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class UnchangedPinException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '기존 비밀번호와 다른 비밀번호를 입력해주세요.')
    {
        parent::__construct($message);
    }
}
