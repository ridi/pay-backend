<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use RidiPay\Tests\TestUtil;
use RidiPay\User\Domain\Service\PinEntryAbuseBlockPolicy;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

class ValidatePinTest extends ControllerTestCase
{
    private const VALID_PIN = '123456';
    private const INVALID_PIN = '654321';

    /** @var Client */
    private static $client;

    /** @var int */
    private static $u_idx;

    public static function setUpBeforeClass()
    {
        self::$u_idx = TestUtil::getRandomUidx();
        UserAppService::createUser(self::$u_idx);

        self::$client = self::createClientWithOAuth2AccessToken();
        TestUtil::setUpOAuth2Doubles(self::$u_idx, TestUtil::U_ID);
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownOAuth2Doubles();
    }

    public function testEnterPinCorrectly()
    {
        UserAppService::updatePin(self::$u_idx, self::VALID_PIN);

        $body = json_encode(['pin' => self::VALID_PIN]);
        self::$client->request('POST', '/me/pin/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
    }

    public function testEnterPinIncorrectly()
    {
        UserAppService::updatePin(self::$u_idx, self::VALID_PIN);

        // PIN 입력 불일치
        $policy = new PinEntryAbuseBlockPolicy();
        for ($try_count = 0; $try_count < $policy->getBlockThreshold() - 1; $try_count++) {
            $body = json_encode(['pin' => self::INVALID_PIN]);
            self::$client->request('POST', '/me/pin/validate', [], [], [], $body);
            $this->assertSame(Response::HTTP_BAD_REQUEST, self::$client->getResponse()->getStatusCode());
        }

        // PIN 연속 입력 불일치 => 일정 시간 입력 제한
        $body = json_encode(['pin' => self::INVALID_PIN]);
        self::$client->request('POST', '/me/pin/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());

        // 일정 시간 입력 제한 이후 시도
        $body = json_encode(['pin' => self::INVALID_PIN]);
        self::$client->request('POST', '/me/pin/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());
    }
}
