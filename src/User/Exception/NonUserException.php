<?php
declare(strict_types=1);

namespace RidiPay\User\Exception;

class NonUserException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = 'RIDI Pay 사용자가 아닙니다.')
    {
        parent::__construct($message);
    }
}
