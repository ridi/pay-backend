<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Constant;

abstract class PgConstant
{
    public const STATUS_ACTIVE = 'ACTIVE'; // 사용
    public const STATUS_INACTIVE = 'INACTIVE'; // 미사용
    public const STATUS_KEPT = 'KEPT'; // 기존 유저는 사용, 신규 유저는 미사용

    public const KCP = 'KCP';
}
