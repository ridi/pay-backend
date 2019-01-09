<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class NotFoundUserException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '이용자가 아닙니다.')
    {
        parent::__construct($message);
    }
}
