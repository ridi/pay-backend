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

class UpdatePinTest extends WebTestCase
{
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

    public function testUpdateValidPin()
    {
        $body = json_encode(['pin' => self::getValidPin()]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/pin', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
    }

    public function testPreventUpdatingInvalidPinWithShortLength()
    {
        $body = json_encode(['pin' => self::getInvalidPinWithShortLength()]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/pin', [], [], [], $body);
        $this->assertSame(Response::HTTP_BAD_REQUEST, self::$client->getResponse()->getStatusCode());
    }

    public function testPreventUpdatingInvalidPinIncludingUnsupportedCharacters()
    {
        $body = json_encode(['pin' => self::getInvalidPinIncludingUnsupportedCharacters()]);
        self::$client->request('PUT', '/users/' . self::U_ID . '/pin', [], [], [], $body);
        $this->assertSame(Response::HTTP_BAD_REQUEST, self::$client->getResponse()->getStatusCode());
    }

    /**
     * @return string
     */
    private static function getValidPin(): string
    {
        return substr(str_shuffle('0123456789'), 0, 6);
    }

    /**
     * @return string
     */
    private static function getInvalidPinWithShortLength(): string
    {
        return substr(str_shuffle('0123456789'), 0, 4);
    }

    /**
     * @return string
     */
    private static function getInvalidPinIncludingUnsupportedCharacters(): string
    {
        $supported_characters = substr(str_shuffle('0123456789'), 0, 4);
        $unsupported_characters = substr(str_shuffle('abcdeefhji'), 0, 2);

        return str_shuffle($supported_characters . $unsupported_characters);
    }
}
