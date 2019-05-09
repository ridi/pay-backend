<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use GuzzleHttp\Client;
use RidiPay\Library\Jwt\JwtAuthorizationHelper;
use RidiPay\Library\Jwt\JwtAuthorizationServiceNameConstant;

class RidiSelectSubscriptionPaymentMethodChangeNotifier
{
    private const API_TIME_OUT = 10;

    /**
     * @param int $u_idx
     * @param string $subscription_uuid
     * @throws \Exception
     */
    public static function notify(int $u_idx, string $subscription_uuid): void
    {
        $client = self::createClient();
        $data = ['bill_key' => $subscription_uuid];
        $headers = JwtAuthorizationHelper::getAuthorizationHeader(
            JwtAuthorizationServiceNameConstant::RIDI_PAY,
            JwtAuthorizationServiceNameConstant::RIDISELECT
        );
        $client->put(
            "/api/select/users/{$u_idx}/subscription",
            [
                'json' => $data,
                'headers' => $headers
            ]
        );
    }

    /**
     * @return Client
     */
    private static function createClient(): Client
    {
        return new Client([
            'base_uri' => getenv('RIDIBOOKS_SERVER_HOST', true),
            'connect_timeout' => self::API_TIME_OUT,
            'timeout' => self::API_TIME_OUT,
        ]);
    }
}