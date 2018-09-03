<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Exception;

class UnsupportedPgException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '지원하지 않는 PG 서비스입니다.')
    {
        parent::__construct($message);
    }
}
