<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use RidiPay\User\Domain\Entity\PaymentMethodEntity;

abstract class PaymentMethodDto
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
