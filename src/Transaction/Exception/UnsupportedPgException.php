<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Exception;

class UnsupportedPgException extends \Exception
{
    public function __construct(string $message = '지원하지 않는 PG 서비스입니다.')
    {
        parent::__construct($message);
    }
}
