<?php
declare(strict_types=1);

namespace RidiPay\Tests\Action;

use AspectMock\Test as test;
use Ridibooks\OAuth2\Constant\AccessTokenConstant;
use RidiPay\Library\OAuth2\User\DefaultUserProvider;
use RidiPay\Library\OAuth2\User\User;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

class UpdateOnetouchPayTest extends WebTestCase
{
    private const U_ID = 'ridipay';

    /** @var Client */
    private static $client;

    /** @var int */
    private static $u_idx;

    public static function setUpBeforeClass()
    {
        self::$u_idx = TestUtil::getRandomUidx();

        test::double(
            DefaultUserProvider::class,
            [
                'getUser' => new User(json_encode([
                    'result' => [
                        'id' => self::U_ID,
                        'idx' => self::$u_idx,
                        'is_verified_adult' => true,
                    ],
                    'message' => '정상적으로 완료되었습니다.'
                ]))
            ]
        );

        $cookie = new Cookie(
            AccessTokenConstant::ACCESS_TOKEN_COOKIE_KEY,
            getenv('OAUTH2_ACCESS_TOKEN')
        );
        self::$client = static::createClient();
        self::$client->getCookieJar()->set($cookie);
    }

    protected function setUp()
    {
        TestUtil::setUpDatabaseDoubles();

        UserAppService::createUserIfNotExists(self::$u_idx);
    }

    public static function tearDownAfterClass()
    {
        test::clean(DefaultUserProvider::class);

        TestUtil::tearDownDatabaseDoubles();
    }

    public function testEnableOnetouchPayWhenAddingFirstPaymentMethod()
    {
        $body = json_encode(['enable_onetouch_pay' => true]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertTrue(UserAppService::isUsingOnetouchPay(self::$u_idx));
    }

    public function testDisableOnetouchPayWhenAddingFirstPaymentMethodAndNotHavingPin()
    {
        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());
    }

    public function testDisableOnetouchPayWhenAddingFirstPaymentMethodAndHavingPin()
    {
        UserAppService::updatePin(self::$u_idx, '123456');

        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertFalse(UserAppService::isUsingOnetouchPay(self::$u_idx));
    }

    public function testEnableOnetouchPay()
    {
        UserAppService::updatePin(self::$u_idx, '123456');

        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertFalse(UserAppService::isUsingOnetouchPay(self::$u_idx));

        $body = json_encode(['enable_onetouch_pay' => true]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertTrue(UserAppService::isUsingOnetouchPay(self::$u_idx));
    }

    public function testDisableOnetouchPay()
    {
        UserAppService::updatePin(self::$u_idx, '123456');

        $body = json_encode(['enable_onetouch_pay' => true]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertTrue(UserAppService::isUsingOnetouchPay(self::$u_idx));

        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertFalse(UserAppService::isUsingOnetouchPay(self::$u_idx));
    }
}
