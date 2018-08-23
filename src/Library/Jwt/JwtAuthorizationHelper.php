<?php
declare(strict_types=1);

namespace RidiPay\Library\Jwt;

use Firebase\JWT\JWT;

class JwtAuthorizationHelper
{
    private const SIGNING_ALGORITHM = 'RS256';

    /**
     * @param string $iss
     * @param string $aud
     * @param string|null $sub
     * @return array
     * @throws \Exception
     */
    public static function getAuthorizationHeader(string $iss, string $aud, ?string $sub = null): array
    {
        $token = self::encodeJwt($iss, $aud, $sub);
        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }

    /**
     * @param string $iss
     * @param string $aud
     * @param string|null $sub
     * @return string
     * @throws \Exception
     */
    public static function encodeJwt(string $iss, string $aud, ?string $sub = null): string
    {
        $rsa_private_key = self::getKey($iss, $aud, false);
        if (is_null($rsa_private_key)) {
            throw new \Exception("RSA private key doesn't exist.");
        }

        $payload = [
            'iss' => $iss,
            'aud' => $aud
        ];
        if (!is_null($sub)) {
            $payload['sub'] = $sub;
        }

        return JWT::encode($payload, $rsa_private_key, self::SIGNING_ALGORITHM);
    }

    /**
     * @param string $iss
     * @param string $aud
     * @param bool $is_public
     * @return string|null
     */
    public static function getKey(string $iss, string $aud, bool $is_public): ?string
    {
        $service_part = "{$iss}_to_{$aud}";
        $key_type = ($is_public ? 'public' : 'private') . '_key';

        $key_name = strtoupper("{$service_part}_{$key_type}");
        $key = \getenv($key_name);

        return empty($key) ? null : $key;
    }
}
