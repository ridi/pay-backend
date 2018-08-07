<?php
declare(strict_types=1);

namespace RidiPay\User\Service;

use RidiPay\User\Dto\PaymentMethodsDto;
use RidiPay\User\Repository\PaymentMethodRepository;

class PaymentMethodService
{
    /**
     * @param int $u_idx
     * @return \RidiPay\User\Dto\PaymentMethodsDto
     */
    public static function getPaymentMethods(int $u_idx): PaymentMethodsDto
    {
        $payment_methods = PaymentMethodRepository::getRepository()->getPaymentMethods($u_idx);

        return new PaymentMethodsDto($payment_methods);
    }
}
