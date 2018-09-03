<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain;

class PgConstant
{
    public const STATUS_ACTIVE = 'ACTIVE'; // 사용
    public const STATUS_INACTIVE = 'INACTIVE'; // 미사용
    public const STATUS_KEPT = 'KEPT'; // 기존 유저는 사용, 신규 유저는 미사용

    public const PAYABLE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_KEPT
    ];

    public const KCP = 'KCP';

    public const AVAILABLE_PGS = [
        self::KCP
    ];
}
