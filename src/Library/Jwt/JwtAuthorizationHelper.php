<?php
declare(strict_types=1);

namespace RidiPay\Library\Jwt;

use Firebase\JWT\JWT;
use RidiPay\Library\TimeUnitConstant;

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
            throw new \Exception("RSA private key doesn't exist");
        }

        $payload = [
            'iss' => $iss,
            'aud' => $aud,
            'exp' => time() + (5 * TimeUnitConstant::SEC_IN_MINUTE)
        ];
        if (!is_null($sub)) {
            $payload['sub'] = $sub;
        }

        return JWT::encode($payload, $rsa_private_key, self::SIGNING_ALGORITHM);
    }

    /**
     * @param string $jwt
     * @param string[] $isses
     * @param string $aud
     * @return object
     * @throws \Exception
     */
    public static function decodeJwt(string $jwt, array $isses, string $aud)
    {
        $payload = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $jwt)[1]));

        if (!isset($payload->iss) || !in_array($payload->iss, $isses, true)) {
            throw new \Exception('Invalid iss');
        }

        if (!isset($payload->aud) || ($payload->aud !== $aud)) {
            throw new \Exception('Invalid aud');
        }

        $rsa_public_key = self::getKey($payload->iss, $aud, true);
        if (is_null($rsa_public_key)) {
            throw new \Exception("RSA public key doesn't exist");
        }

        return JWT::decode($jwt, $rsa_public_key, [self::SIGNING_ALGORITHM]);
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

        /**
         * 환경 변수 이름 규칙
         *   - hyphen을 지원하지 않아서 underscore로 치환
         *   - 대문자 이용
         */
        $key_name = str_replace('-', '_', strtoupper("{$service_part}_{$key_type}"));
        $key = str_replace("\\n", "\n", getenv($key_name, true));

        return empty($key) ? null : $key;
    }
}
