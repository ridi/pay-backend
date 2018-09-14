<?php
declare(strict_types=1);

namespace RidiPay\Tests\Jwt;

use RidiPay\Library\Jwt\JwtAuthorizationHelper;
use RidiPay\Library\Jwt\JwtAuthorizationServiceNameConstant;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthorizationMiddlewareTest extends WebTestCase
{
    private const JWT_ISS_DUMMY = 'dummy';

    /**
     * @param array $options
     * @return DummyKernel
     */
    protected static function createKernel(array $options = []): DummyKernel
    {
        return new DummyKernel(getenv('APP_ENV'), true);
    }

    /**
     * @dataProvider requestHeaderProvider
     *
     * @param array $header
     * @param int $http_status_code
     */
    public function testMiddleware(array $header, int $http_status_code)
    {
        $client = self::createClient([], $header);
        $client->request(Request::METHOD_GET, '/');
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function requestHeaderProvider(): array
    {
        $private_key = file_get_contents(__DIR__ . '/dummy.key');
        $public_key = file_get_contents(__DIR__ . '/dummy.key.pub');
        putenv("DUMMY_TO_RIDI_PAY_PRIVATE_KEY={$private_key}");
        putenv("DUMMY_TO_RIDI_PAY_PUBLIC_KEY={$public_key}");

        $jwt = JwtAuthorizationHelper::encodeJwt(self::JWT_ISS_DUMMY, JwtAuthorizationServiceNameConstant::RIDI_PAY);

        return [
            [['HTTP_Authorization' => "Bearer {$jwt}"], Response::HTTP_OK],
            [['HTTP_Authorization' => 'Bearer abcde'], Response::HTTP_UNAUTHORIZED],
            [['HTTP_Authorization' => '12345'], Response::HTTP_UNAUTHORIZED],
            [[], Response::HTTP_UNAUTHORIZED]
        ];
    }
}
