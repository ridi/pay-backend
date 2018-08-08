<?php
declare(strict_types=1);

namespace RidiPay\User\Exception;

class LeavedUserException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '탈퇴한 사용자입니다.')
    {
        parent::__construct($message);
    }
}
