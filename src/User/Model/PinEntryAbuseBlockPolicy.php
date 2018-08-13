<?php
declare(strict_types=1);

namespace RidiPay\User\Model;

use Ridibooks\Library\TimeConstant;

class PinEntryAbuseBlockPolicy implements BaseAbuseBlockPolicy
{
    public function getAbuseType()
    {
        return 'pin-entry';
    }

    public function getBlockedPeriod()
    {
        return 10 * TimeConstant::SEC_IN_MINUTE;
    }

    public function getBlockThreshold()
    {
        return 5;
    }
}
