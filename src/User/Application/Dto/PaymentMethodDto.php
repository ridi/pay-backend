<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\PaymentMethodConstant;

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

    /**
     * @return string
     * @throws UnsupportedPaymentMethodException
     */
    public function getType(): string
    {
        if ($this instanceof CardDto) {
            return PaymentMethodConstant::TYPE_CARD;
        } else {
            throw new UnsupportedPaymentMethodException();
        }
    }

    /**
     * @return bool
     * @throws UnsupportedPaymentMethodException
     */
    public function isCard(): bool
    {
        return $this->getType() === PaymentMethodConstant::TYPE_CARD;
    }
}
