<?php
declare(strict_types=1);

namespace RidiPay\User\Domain;

class PaymentMethodConstant
{
    public const TYPE_CARD = 'CARD'; // 카드

    public const CARD_PAYMENT_KEY_PURPOSE_ONE_TIME = 'ONE_TIME'; // 소득 공제 가능 단건 결제
    public const CARD_PAYMENT_KEY_PURPOSE_ONE_TIME_TAX_DEDUCTION = 'ONE_TIME_TAX_DEDUCTION'; // 소득 공제 불가능 단건 결제
    public const CARD_PAYMENT_KEY_PURPOSE_BILLING = 'BILLING'; // 정기 결제
}
