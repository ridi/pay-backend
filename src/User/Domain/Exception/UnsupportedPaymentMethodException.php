<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class UnsupportedPaymentMethodException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '지원하지 않는 결제 수단입니다.')
    {
        parent::__construct($message);
    }
}
