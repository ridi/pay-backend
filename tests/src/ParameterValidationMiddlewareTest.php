<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use Ramsey\Uuid\Uuid;
use RidiPay\Tests\Dummy\DummyKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ParameterValidationMiddlewareTest extends WebTestCase
{
    /**
     * @param array $options
     * @return DummyKernel
     */
    protected static function createKernel(array $options = []): DummyKernel
    {
        return new DummyKernel(getenv('APP_ENV'), true);
    }

    /**
     * @dataProvider requestBodyProvider
     *
     * @param string $body
     * @param int $http_status_code
     */
    public function testMiddleware(string $body, int $http_status_code)
    {
        $client = self::createClient([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_POST, '/param-validator', [], [], [], $body);
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function requestBodyProvider(): array
    {
        return [
            // 정상 입력
            [
                json_encode([
                    'digits' => random_int(1, 999999),
                    'not_blank_string' => '전자책',
                    'boolean' => true,
                    'uuid' => Uuid::uuid4()->toString(),
                    'url' => 'https://ridibooks.com/payment/callback/ridi-pay/2018010100000000',
                    'card_number' => '5164531234567890',
                    'card_expiration_date' => '2511',
                    'tax_id' => '940101'
                ]),
                Response::HTTP_OK
            ],
            // 올바르지 않은 'digits'
            [
                json_encode([
                    'digits' => 'abcde',
                    'not_blank_string' => '전자책',
                    'boolean' => true,
                    'uuid' => Uuid::uuid4()->toString(),
                    'url' => 'https://ridibooks.com/payment/callback/ridi-pay/2018010100000000',
                    'card_number' => '5164531234567890',
                    'card_expiration_date' => '2511',
                    'tax_id' => '940101'
                ]),
                Response::HTTP_BAD_REQUEST
            ],
            // 올바르지 않은 'not_blank_string'
            [
                json_encode([
                    'digits' => random_int(1, 999999),
                    'not_blank_string' => '',
                    'boolean' => true,
                    'uuid' => Uuid::uuid4()->toString(),
                    'url' => 'https://ridibooks.com/payment/callback/ridi-pay/2018010100000000',
                    'card_number' => '5164531234567890',
                    'card_expiration_date' => '2511',
                    'tax_id' => '940101'
                ]),
                Response::HTTP_BAD_REQUEST
            ],
            // 올바르지 않은 'boolean'
            [
                json_encode([
                    'digits' => random_int(1, 999999),
                    'not_blank_string' => '전자책',
                    'boolean' => 'true',
                    'uuid' => Uuid::uuid4()->toString(),
                    'url' => 'https://ridibooks.com/payment/callback/ridi-pay/2018010100000000',
                    'card_number' => '5164531234567890',
                    'card_expiration_date' => '2511',
                    'tax_id' => '940101'
                ]),
                Response::HTTP_BAD_REQUEST
            ],
            // 올바르지 않은 'uuid'
            [
                json_encode([
                    'digits' => random_int(1, 999999),
                    'not_blank_string' => '전자책',
                    'boolean' => true,
                    'uuid' => Uuid::uuid4()->getHex(),
                    'url' => 'https://ridibooks.com/payment/callback/ridi-pay/2018010100000000',
                    'card_number' => '5164531234567890',
                    'card_expiration_date' => '2511',
                    'tax_id' => '940101'
                ]),
                Response::HTTP_BAD_REQUEST
            ],
            // 올바르지 않은 'url'
            [
                json_encode([
                    'digits' => random_int(1, 999999),
                    'not_blank_string' => '전자책',
                    'boolean' => true,
                    'uuid' => Uuid::uuid4()->toString(),
                    'url' => 'https:/wrong.',
                    'card_number' => '5164531234567890',
                    'card_expiration_date' => '2511',
                    'tax_id' => '940101'
                ]),
                Response::HTTP_BAD_REQUEST
            ],
            // 올바르지 않은 'card_number'
            [
                json_encode([
                    'digits' => random_int(1, 999999),
                    'not_blank_string' => '전자책',
                    'boolean' => true,
                    'uuid' => Uuid::uuid4()->toString(),
                    'url' => 'https://ridibooks.com/payment/callback/ridi-pay/2018010100000000',
                    'card_number' => "5164531234567890;echo 'Hello, World!'",
                    'card_expiration_date' => '2511',
                    'tax_id' => '940101'
                ]),
                Response::HTTP_BAD_REQUEST
            ],
            // 올바르지 않은 'card_expiration_date'
            [
                json_encode([
                    'digits' => random_int(1, 999999),
                    'not_blank_string' => '전자책',
                    'boolean' => true,
                    'uuid' => Uuid::uuid4()->toString(),
                    'url' => 'https://ridibooks.com/payment/callback/ridi-pay/2018010100000000',
                    'card_number' => '5164531234567890',
                    'card_expiration_date' => '1125',
                    'tax_id' => '940101'
                ]),
                Response::HTTP_BAD_REQUEST
            ],
            // 올바르지 않은 'tax_id'
            [
                json_encode([
                    'digits' => random_int(1, 999999),
                    'not_blank_string' => '전자책',
                    'boolean' => true,
                    'uuid' => Uuid::uuid4()->toString(),
                    'url' => 'https://ridibooks.com/payment/callback/ridi-pay/2018010100000000',
                    'card_number' => '5164531234567890',
                    'card_expiration_date' => '1125',
                    'tax_id' => '000001'
                ]),
                Response::HTTP_BAD_REQUEST
            ]
        ];
    }
}
