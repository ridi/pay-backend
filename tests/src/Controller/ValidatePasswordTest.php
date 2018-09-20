<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use AspectMock\Test;
use RidiPay\Library\PasswordValidationApi;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Domain\Service\PasswordEntryAbuseBlockPolicy;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

class ValidatePasswordTest extends ControllerTestCase
{
    private const VALID_PASSWORD = 'abcde@12345';
    private const INVALID_PASSWORD = '12345@abcde';

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

    public function testEnterPasswordCorrectly()
    {
        Test::double(PasswordValidationApi::class, ['isPasswordMatched' => true]);

        $body = json_encode(['password' => self::VALID_PASSWORD]);
        self::$client->request('POST', '/me/password/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        
        Test::clean(PasswordValidationApi::class);
    }

    public function testEnterPasswordIncorrectly()
    {
        Test::double(PasswordValidationApi::class, ['isPasswordMatched' => false]);

        // PASSWORD 입력 불일치
        $policy = new PasswordEntryAbuseBlockPolicy();
        for ($try_count = 0; $try_count < $policy->getBlockThreshold() - 1; $try_count++) {
            $body = json_encode(['password' => self::INVALID_PASSWORD]);
            self::$client->request('POST', '/me/password/validate', [], [], [], $body);
            $this->assertSame(Response::HTTP_BAD_REQUEST, self::$client->getResponse()->getStatusCode());
        }

        // PASSWORD 연속 입력 불일치 => 일정 시간 입력 제한
        $body = json_encode(['password' => self::INVALID_PASSWORD]);
        self::$client->request('POST', '/me/password/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());

        // 일정 시간 입력 제한 이후 시도
        $body = json_encode(['password' => self::INVALID_PASSWORD]);
        self::$client->request('POST', '/me/password/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());
        
        Test::clean(PasswordValidationApi::class);
    }
}
