<?php
declare(strict_types=1);

namespace RidiPay\User\Exception;

class UnknownPaymentMethodException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '등록되지 않은 결제 수단입니다.')
    {
        parent::__construct($message);
    }
}
