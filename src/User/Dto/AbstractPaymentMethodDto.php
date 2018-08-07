<?php
declare(strict_types=1);

namespace RidiPay\User\Dto;

use RidiPay\User\Entity\PaymentMethodEntity;

abstract class AbstractPaymentMethodDto
{
    /** @var string 결제 수단 UUID */
    public $payment_method_id;

    /**
     * @param PaymentMethodEntity $payment_method
     */
    public function __construct(PaymentMethodEntity $payment_method)
    {
        $this->payment_method_id = $payment_method->getUuid()->toString();
    }
}
