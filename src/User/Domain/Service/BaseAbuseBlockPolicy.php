<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

interface BaseAbuseBlockPolicy
{
    /** @return string Abuse 종류 */
    public function getAbuseType(): string;

    /** @return int 차단 기간(seconds) */
    public function getBlockedPeriod(): int;

    /** @return int 차단 임계값 */
    public function getBlockThreshold(): int;
}
