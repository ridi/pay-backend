<?php
declare(strict_types=1);

namespace RidiPay\User\Constant;

abstract class CardPurposeConstant
{
    public const ONE_TIME = 'ONE_TIME'; // 단건 결제
    public const BILLING = 'BILLING'; // 정기 결제

    public const AVAILABLE_PURPOSE = [
        self::ONE_TIME,
        self::BILLING
    ];
}
