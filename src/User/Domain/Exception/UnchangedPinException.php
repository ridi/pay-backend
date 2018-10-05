<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class UnchangedPinException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '현재 비밀번호와 동일합니다.')
    {
        parent::__construct($message);
    }
}
