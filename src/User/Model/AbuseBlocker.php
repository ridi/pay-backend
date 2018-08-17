<?php
declare(strict_types=1);

namespace RidiPay\User\Model;

use Predis\Client;

class AbuseBlocker
{
    private const FIELD_TRY_COUNT = 'try_count';
    private const FIELD_BLOCKED_AT = 'blocked_at';

    /** @var BaseAbuseBlockPolicy */
    private $policy;

    /** @var Client */
    private $redis;

    /** @var int */
    private $u_idx;

    /**
     * @param BaseAbuseBlockPolicy $policy
     * @param $u_idx
     */
    public function __construct(BaseAbuseBlockPolicy $policy, int $u_idx)
    {
        $this->policy = $policy;
        $this->redis = new Client(['host' => getenv('REDIS_HOST')]);
    }

    /**
     * @return bool
     */
    public function isBlocked(): bool
    {
        $key = $this->getKey();
        $try_count = $this->redis->hincrby($this->getKey(), self::FIELD_TRY_COUNT, 1);
        if ($try_count === $this->policy->getBlockThreshold()) {
            $blocked_at = time();
            $this->redis->hsetnx($key, self::FIELD_BLOCKED_AT, $blocked_at);
            $this->redis->expireat($key, $blocked_at + $this->policy->getBlockedPeriod());

            return true;
        }

        return false;
    }

    /**
     * @return int Unix Timestamp
     */
    public function getBlockedAt(): int
    {
        return intval($this->redis->hget($this->getKey(), self::FIELD_BLOCKED_AT));
    }

    /**
     * @return string
     */
    private function getKey(): string
    {
        return $this->policy->getAbuseType() . ':' . $this->u_idx;
    }
}
