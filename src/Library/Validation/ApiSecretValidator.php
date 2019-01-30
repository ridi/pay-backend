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
     * @return ApiSecret
     * @throws ApiSecretValidationException
     */
    public static function validate(Request $request): ApiSecret
    {
        $api_key = $request->headers->get(self::HEADER_API_KEY);
        $secret_key = $request->headers->get(self::HEADER_SECRET_KEY);
        if (!is_string($api_key) || !is_string($secret_key)) {
            throw new ApiSecretValidationException();
        }

        $validator = Validation::createValidator();
        $constraint = new Uuid();

        $api_key_violations = $validator->validate($api_key, $constraint);
        $secret_key_violations = $validator->validate($secret_key, $constraint);
        if (count($api_key_violations) > 0 || count($secret_key_violations) > 0) {
            throw new ApiSecretValidationException();
        }

        return new ApiSecret($api_key, $secret_key);
    }
}
