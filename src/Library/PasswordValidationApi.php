<?php
declare(strict_types=1);

namespace RidiPay\Library;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use RidiPay\Library\Jwt\JwtAuthorizationHelper;
use RidiPay\Library\Jwt\JwtAuthorizationServiceNameConstant;

class PasswordValidationApi
{
    private const API_TIME_OUT = 5;

    /**
     * @param string $u_id
     * @param string $password
     * @return bool
     * @throws \Exception
     */
    public static function isPasswordMatched(string $u_id, string $password): bool
    {
        $client = self::createClient();
        $data = [
            'u_id' => $u_id,
            'password' => $password
        ];
        $headers = JwtAuthorizationHelper::getAuthorizationHeader(
            JwtAuthorizationServiceNameConstant::RIDI_PAY,
            JwtAuthorizationServiceNameConstant::STORE
        );

        try {
            $client->post(
                '/api/account/is-loginable',
                [
                    'json' => $data,
                    'headers' => $headers
                ]
            );
        } catch (ClientException $e) {
            // HTTP 4XX Response
            return false;
        } catch (\Exception $e) {
            SentryHelper::getClient()->captureException($e);
            return false;
        }

        return true;
    }

    /**
     * @return Client
     */
    private static function createClient(): Client
    {
        return new Client([
            'base_uri' => getenv('RIDIBOOKS_SERVER_HOST'),
            'timeout' => self::API_TIME_OUT,
        ]);
    }
}
