<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class DeletedPaymentMethodException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '삭제된 결제 수단입니다.')
    {
        parent::__construct($message);
    }
}
