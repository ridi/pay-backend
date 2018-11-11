<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

class UnderMinimumPaymentAmountException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '최소 결제 금액은 ' . Order::GOOD_PRICE_KRW_MIN . '원입니다.')
    {
        parent::__construct($message);
    }
}
