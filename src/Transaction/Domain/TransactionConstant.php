<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain;

class TransactionConstant
{
    public const STATUS_RESERVED = 'RESERVED'; // 예약
    public const STATUS_APPROVED = 'APPROVED'; // 승인
    public const STATUS_CANCELED = 'CANCELED'; // 취소
}
