<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\CardAppService;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateOnetouchPayTest extends ControllerTestCase
{
    /** @var Client */
    private static $client;

    public function testEnableOnetouchPayWhenAddingFirstPaymentMethod()
    {
        $u_idx = TestUtil::getRandomUidx();
        CardAppService::registerCard(
            $u_idx,
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );
        UserAppService::createPin($u_idx, '123456');

        self::$client = self::createClientWithOAuth2AccessToken();
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $body = json_encode(['enable_onetouch_pay' => true]);
        self::$client->request(Request::METHOD_POST, '/me/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertTrue(UserAppService::isUsingOnetouchPay($u_idx));

        TestUtil::tearDownOAuth2Doubles();
    }

    public function testEnableOnetouchPay()
    {
        $pin = '123456';

        $u_idx = TestUtil::getRandomUidx();
        TestUtil::signUp(
            $u_idx,
            $pin,
            false,
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );

        self::$client = self::createClientWithOAuth2AccessToken();
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request(Request::METHOD_PUT, '/me/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertFalse(UserAppService::isUsingOnetouchPay($u_idx));

        $body = json_encode(['pin' => $pin]);
        self::$client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $body);
        $validation_token = json_decode(self::$client->getResponse()->getContent())->validation_token;

        $body = json_encode([
            'enable_onetouch_pay' => true,
            'validation_token' => $validation_token
        ]);
        self::$client->request(Request::METHOD_PUT, '/me/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertTrue(UserAppService::isUsingOnetouchPay($u_idx));

        TestUtil::tearDownOAuth2Doubles();
    }

    public function testDisableOnetouchPay()
    {
        $pin = '123456';

        $u_idx = TestUtil::getRandomUidx();
        TestUtil::signUp(
            $u_idx,
            $pin,
            true,
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );

        self::$client = self::createClientWithOAuth2AccessToken();
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $body = json_encode(['pin' => $pin]);
        self::$client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $body);
        $validation_token = json_decode(self::$client->getResponse()->getContent())->validation_token;

        $body = json_encode([
            'enable_onetouch_pay' => true,
            'validation_token' => $validation_token
        ]);
        self::$client->request(Request::METHOD_PUT, '/me/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertTrue(UserAppService::isUsingOnetouchPay($u_idx));

        $body = json_encode(['enable_onetouch_pay' => false]);
        self::$client->request(Request::METHOD_PUT, '/me/onetouch', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertFalse(UserAppService::isUsingOnetouchPay($u_idx));

        TestUtil::tearDownOAuth2Doubles();
    }
}
