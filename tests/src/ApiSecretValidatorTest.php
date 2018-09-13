<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\Validation\ApiSecretValidationException;
use RidiPay\Library\Validation\ApiSecretValidator;
use Symfony\Component\HttpFoundation\Request;

class ApiSecretValidatorTest extends TestCase
{
    /**
     * @dataProvider requestProvider
     *
     * @param Request $request
     * @param null|string $expected_exception_class
     * @throws ApiSecretValidationException
     */
    public function testValidation(Request $request, ?string $expected_exception_class)
    {
        if (!is_null($expected_exception_class)) {
            $this->expectException($expected_exception_class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        ApiSecretValidator::validate($request);
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function requestProvider(): array
    {
        return [
            [
                new Request(
                    [],
                    [],
                    [],
                    [],
                    [],
                    [
                        'HTTP_' . ApiSecretValidator::HEADER_API_KEY => Uuid::uuid4()->toString(),
                        'HTTP_' . ApiSecretValidator::HEADER_SECRET_KEY => Uuid::uuid4()->toString()
                    ]
                ),
                null
            ],
            [
                new Request(
                    [],
                    [],
                    [],
                    [],
                    [],
                    [
                        'HTTP_' . ApiSecretValidator::HEADER_API_KEY => 'abcde',
                        'HTTP_' . ApiSecretValidator::HEADER_SECRET_KEY => '12345'
                    ]
                ),
                ApiSecretValidationException::class
            ],
            [
                new Request(),
                ApiSecretValidationException::class
            ]
        ];
    }
}
