<?php
declare(strict_types=1);

namespace RidiPay\Library\Validation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\Validation;

class ApiSecretValidator
{
    public const HEADER_API_KEY = 'Api-Key';
    public const HEADER_SECRET_KEY = 'Secret-Key';

    /**
     * @param Request $request
     * @throws ApiSecretValidationException
     */
    public static function validate(Request $request): void
    {
        $api_key = self::getApiKey($request);
        $secret_key = self::getSecretKey($request);
        if (is_null($api_key) || is_null($secret_key)) {
            throw new ApiSecretValidationException();
        }

        $validator = Validation::createValidator();
        $constraint = new Uuid();

        $api_key_violations = $validator->validate($api_key, $constraint);
        $secret_key_violations = $validator->validate($secret_key, $constraint);
        if (count($api_key_violations) > 0 || count($secret_key_violations) > 0) {
            throw new ApiSecretValidationException();
        }
    }

    /**
     * @param Request $request
     * @return null|string
     */
    public static function getApiKey(Request $request): ?string
    {
        return $request->headers->get(self::HEADER_API_KEY);
    }

    /**
     * @param Request $request
     * @return null|string
     */
    public static function getSecretKey(Request $request): ?string
    {
        return $request->headers->get(self::HEADER_SECRET_KEY);
    }
}
