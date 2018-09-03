<?php
declare(strict_types=1);

namespace RidiPay\Tests\Action;

use AspectMock\Test as test;
use Ridibooks\OAuth2\Constant\AccessTokenConstant;
use RidiPay\Library\OAuth2\User\DefaultUserProvider;
use RidiPay\Library\OAuth2\User\User;
use RidiPay\Library\PasswordValidationApi;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Domain\Service\PasswordEntryAbuseBlockPolicy;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ValidatePasswordTest extends WebTestCase
{
    private const VALID_PASSWORD = 'abcde@12345';
    private const INVALID_PASSWORD = '12345@abcde';
    
    private const U_ID = 'ridipay';

    /** @var Client */
    private static $client;

    /** @var int */
    private static $u_idx;

    public static function setUpBeforeClass()
    {
        TestUtil::setUpDatabaseDoubles();

        self::$u_idx = TestUtil::getRandomUidx();
        UserAppService::createUserIfNotExists(self::$u_idx);

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

    public static function tearDownAfterClass()
    {
        test::clean(DefaultUserProvider::class);

        TestUtil::tearDownDatabaseDoubles();
    }

    public function testEnterPasswordCorrectly()
    {
        test::double(PasswordValidationApi::class, ['isPasswordMatched' => true]);

        $body = json_encode(['password' => self::VALID_PASSWORD]);
        self::$client->request('POST', '/users/' . self::U_ID . '/password/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        
        test::clean(PasswordValidationApi::class);
    }

    public function testEnterPasswordIncorrectly()
    {
        test::double(PasswordValidationApi::class, ['isPasswordMatched' => false]);

        // PASSWORD 입력 불일치
        $policy = new PasswordEntryAbuseBlockPolicy();
        for ($try_count = 0; $try_count < $policy->getBlockThreshold() - 1; $try_count++) {
            $body = json_encode(['password' => self::INVALID_PASSWORD]);
            self::$client->request('POST', '/users/' . self::U_ID . '/password/validate', [], [], [], $body);
            $this->assertSame(Response::HTTP_BAD_REQUEST, self::$client->getResponse()->getStatusCode());
        }

        // PASSWORD 연속 입력 불일치 => 일정 시간 입력 제한
        $body = json_encode(['password' => self::INVALID_PASSWORD]);
        self::$client->request('POST', '/users/' . self::U_ID . '/password/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());

        // 일정 시간 입력 제한 이후 시도
        $body = json_encode(['password' => self::INVALID_PASSWORD]);
        self::$client->request('POST', '/users/' . self::U_ID . '/password/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());
        
        test::clean(PasswordValidationApi::class);
    }
}
