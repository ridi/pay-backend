<?php
declare(strict_types=1);

namespace RidiPay\Library;

class DuplicatedRequestException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '이미 처리 중인 요청입니다.')
    {
        parent::__construct($message);
    }
}
