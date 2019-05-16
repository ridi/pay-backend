<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class PaymentMethodChangeDeclinedException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = "결제 시도 중에는 결제 수단을 변경할 수 없습니다. 잠시 후 다시 시도해주세요.")
    {
        parent::__construct($message);
    }
}