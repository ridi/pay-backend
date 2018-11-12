<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use GuzzleHttp\Client;
use RidiPay\Library\Jwt\JwtAuthorizationHelper;
use RidiPay\Library\Jwt\JwtAuthorizationServiceNameConstant;
use RidiPay\Library\SentryHelper;

class RidiCashAutoChargeSubscriptionOptoutManager
{
    private const API_TIME_OUT = 10;

    /**
     * @param int $u_idx
     * @param string $subscription_uuid
     * @throws \Exception
     */
    public static function optout(int $u_idx, string $subscription_uuid): void
    {
        $client = self::createClient();
        $data = ['bill_key' => $subscription_uuid];
        $headers = JwtAuthorizationHelper::getAuthorizationHeader(
            JwtAuthorizationServiceNameConstant::RIDI_PAY,
            JwtAuthorizationServiceNameConstant::STORE
        );
        try {
            $client->delete(
                "/api/users/{$u_idx}/cash-auto-charge",
                [
                    'json' => $data,
                    'headers' => $headers
                ]
            );
        } catch (\Exception $e) {
            SentryHelper::captureException($e);

            throw $e;
        }
    }

    /**
     * @return Client
     */
    private static function createClient(): Client
    {
        return new Client([
            'base_uri' => getenv('RIDIBOOKS_SERVER_HOST'),
            'connect_timeout' => self::API_TIME_OUT,
            'timeout' => self::API_TIME_OUT,
        ]);
    }
}
