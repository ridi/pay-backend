<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

class UpdateOnetouchPayTest extends ControllerTestCase
{
    /** @var Client */
    private static $client;

    /** @var int */
    private static $u_idx;

    public static function setUpBeforeClass()
    {
        TestUtil::setUpDatabaseDoubles();

        self::$u_idx = TestUtil::getRandomUidx();
        UserAppService::createUserIfNotExists(self::$u_idx);

        self::$client = self::createClientWithOAuth2AccessToken();
        TestUtil::setUpOAuth2Doubles(self::$u_idx, TestUtil::U_ID);
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownOAuth2Doubles();
        TestUtil::tearDownDatabaseDoubles();
    }

    public function testEnableOnetouchPayWhenAddingFirstPaymentMethod()
    {
        $body = json_encode(['enable_onetouch_pay' => true]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertTrue(UserAppService::isUsingOnetouchPay(self::$u_idx));
    }

    public function testDisableOnetouchPayWhenAddingFirstPaymentMethodAndNotHavingPin()
    {
        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());
    }

    public function testDisableOnetouchPayWhenAddingFirstPaymentMethodAndHavingPin()
    {
        UserAppService::updatePin(self::$u_idx, '123456');

        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertFalse(UserAppService::isUsingOnetouchPay(self::$u_idx));
    }

    public function testEnableOnetouchPay()
    {
        UserAppService::updatePin(self::$u_idx, '123456');

        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertFalse(UserAppService::isUsingOnetouchPay(self::$u_idx));

        $body = json_encode(['enable_onetouch_pay' => true]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertTrue(UserAppService::isUsingOnetouchPay(self::$u_idx));
    }

    public function testDisableOnetouchPay()
    {
        UserAppService::updatePin(self::$u_idx, '123456');

        $body = json_encode(['enable_onetouch_pay' => true]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertTrue(UserAppService::isUsingOnetouchPay(self::$u_idx));

        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertFalse(UserAppService::isUsingOnetouchPay(self::$u_idx));
    }
}
