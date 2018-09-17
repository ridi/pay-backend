<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use AspectMock\Test;
use Firebase\JWT\JWT;
use Ridibooks\OAuth2\Constant\AccessTokenConstant;
use RidiPay\Library\OAuth2\User\DefaultUserProvider;
use RidiPay\Library\OAuth2\User\User;
use RidiPay\Tests\Dummy\DummyKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OAuth2MiddlewareTest extends WebTestCase
{
    public static function setUpBeforeClass()
    {
        Test::double(
            DefaultUserProvider::class,
            [
                'getUser' => new User(json_encode([
                    'result' => [
                        'idx' => 123456,
                        'id' => 'dummy'
                    ]
                ]))
            ]
        );
    }

    public static function tearDownAfterClass()
    {
        Test::clean(DefaultUserProvider::class);
    }

    /**
     * @param array $options
     * @return DummyKernel
     */
    protected static function createKernel(array $options = []): DummyKernel
    {
        return new DummyKernel(getenv('APP_ENV'), true);
    }

    /**
     * @dataProvider cookieProvider
     *
     * @param null|Cookie $cookie
     * @param int $http_status_code
     */
    public function testMiddleware(?Cookie $cookie, int $http_status_code)
    {
        $client = self::createClient();
        if (!is_null($cookie)) {
            $client->getCookieJar()->set($cookie);
        }

        $client->request(Request::METHOD_GET, '/oauth2');
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function cookieProvider(): array
    {
        return [
            [new Cookie(AccessTokenConstant::ACCESS_TOKEN_COOKIE_KEY, getenv('OAUTH2_ACCESS_TOKEN')), Response::HTTP_OK],
            [new Cookie(AccessTokenConstant::ACCESS_TOKEN_COOKIE_KEY, JWT::encode(['dummy' => '123'], 'dummy_key')), Response::HTTP_UNAUTHORIZED],
            [null, Response::HTTP_UNAUTHORIZED],
        ];
    }
}
