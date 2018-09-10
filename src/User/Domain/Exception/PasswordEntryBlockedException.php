<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

use RidiPay\Library\TimeUnitConstant;
use RidiPay\User\Domain\Service\BaseAbuseBlockPolicy;

class PasswordEntryBlockedException extends \Exception
{
    /**
     * @param BaseAbuseBlockPolicy $policy
     * @param int $remaining_period_for_unblock
     */
    public function __construct(BaseAbuseBlockPolicy $policy, int $remaining_period_for_unblock)
    {
        $block_threshold = $policy->getBlockThreshold();
        $blocked_period_in_minute = floor($policy->getBlockedPeriod() / TimeUnitConstant::SEC_IN_MINUTE);
        $remaining_period_for_unblock_in_minute = $remaining_period_for_unblock / TimeUnitConstant::SEC_IN_MINUTE;
        $message = "비밀번호를 {$block_threshold}회 잘못 입력하셔서 이용이 제한되었습니다. ";
        $message .= "{$blocked_period_in_minute}분 후 다시 이용해주세요. ";
        $message .= "({$remaining_period_for_unblock_in_minute}분 남음)";

        parent::__construct($message);
    }
}
