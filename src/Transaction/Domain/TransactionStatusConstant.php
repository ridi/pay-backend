<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain;

class TransactionStatusConstant
{
    public const RESERVED = 'RESERVED'; // 예약
    public const APPROVED = 'APPROVED'; // 승인
    public const CANCELED = 'CANCELED'; // 취소
}
