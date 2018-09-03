<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class UnmatchedPinException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '결제 비밀번호를 올바르게 입력해주세요.')
    {
        parent::__construct($message);
    }
}
