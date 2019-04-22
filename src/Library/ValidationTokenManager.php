<?php
declare(strict_types=1);

namespace RidiPay\Library;

use Predis\Client;
use Ramsey\Uuid\Uuid;

class ValidationTokenManager
{
    private const FIELD_NAME = 'validation_token';

    /**
     * @param string $key
     * @param int $ttl
     * @return string
     * @throws \Exception
     */
    public static function generate(string $key, int $ttl): string
    {
        $validation_token = Uuid::uuid4()->toString();

        $redis = self::getRedisClient();
        $redis->hset($key, self::FIELD_NAME, $validation_token);
        $redis->expire($key, $ttl);

        return $validation_token;
    }

    /**
     * @param string $key
     * @return null|string
     */
    public static function get(string $key): ?string
    {
        $redis = self::getRedisClient();
        return $redis->hget($key, self::FIELD_NAME);
    }

    /**
     * @param string $key
     */
    public static function invalidate(string $key): void
    {
        $redis = self::getRedisClient();
        $redis->hdel($key, [self::FIELD_NAME]);
    }

    /**
     * @return Client
     */
    private static function getRedisClient(): Client
    {
        return new Client(['host' => getenv('REDIS_HOST', true)]);
    }
}
