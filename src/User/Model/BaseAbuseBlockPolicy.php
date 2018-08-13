<?php
declare(strict_types=1);

namespace RidiPay\User\Model;

interface BaseAbuseBlockPolicy
{
    /** @return string Abuse 종류 */
    public function getAbuseType();

    /** @return int 차단 기간(seconds) */
    public function getBlockedPeriod();

    /** @var int 차단 임계값 */
    public function getBlockThreshold();
}
