<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Service;

use Ridibooks\Library\TimeConstant;

class PinEntryAbuseBlockPolicy implements BaseAbuseBlockPolicy
{
    /**
     * @return string
     */
    public function getAbuseType(): string
    {
        return 'pin-entry';
    }

    /**
     * @return int
     */
    public function getBlockedPeriod(): int
    {
        return 10 * TimeConstant::SEC_IN_MINUTE;
    }

    /**
     * @return int
     */
    public function getBlockThreshold(): int
    {
        return 5;
    }
}
