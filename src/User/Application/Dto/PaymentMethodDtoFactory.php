<?php
declare(strict_types=1);

namespace RidiPay\User\Application\Dto;

use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\PaymentMethodConstant;

class PaymentMethodDtoFactory
{
    /**
     * @param PaymentMethodEntity $payment_method
     * @return PaymentMethodDto
     * @throws UnsupportedPaymentMethodException
     */
    public static function create(PaymentMethodEntity $payment_method): PaymentMethodDto
    {
        switch ($payment_method->getType()) {
            case PaymentMethodConstant::TYPE_CARD:
                // 단건 결제용 카드와 정기 결제용 카드는 연동 PG사 분기 가능성을 고려해서 나눠진 것 뿐이고,
                // 두 카드는 동일한 카드라서 단건 결제용 카드를 외부로 전달한다.
                return new CardDto($payment_method->getCardForOneTimePayment());
            default:
                throw new UnsupportedPaymentMethodException();
        }
    }
}
