<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

class UpdatePinTest extends ControllerTestCase
{
    /** @var Client */
    private static $client;

    /** @var int */
    private static $u_idx;

    public static function setUpBeforeClass()
    {
        TestUtil::setUpDatabaseDoubles();

        self::$u_idx = TestUtil::getRandomUidx();
        UserAppService::createUser(self::$u_idx);

        self::$client = self::createClientWithOAuth2AccessToken();
        TestUtil::setUpOAuth2Doubles(self::$u_idx, TestUtil::U_ID);
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownOAuth2Doubles();
        TestUtil::tearDownDatabaseDoubles();
    }

    public function testUpdateValidPin()
    {
        $body = json_encode(['pin' => self::getValidPin()]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/pin', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
    }

    public function testPreventUpdatingInvalidPinWithShortLength()
    {
        $body = json_encode(['pin' => self::getInvalidPinWithShortLength()]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/pin', [], [], [], $body);
        $this->assertSame(Response::HTTP_BAD_REQUEST, self::$client->getResponse()->getStatusCode());
    }

    public function testPreventUpdatingInvalidPinIncludingUnsupportedCharacters()
    {
        $body = json_encode(['pin' => self::getInvalidPinIncludingUnsupportedCharacters()]);
        self::$client->request('PUT', '/users/' . TestUtil::U_ID . '/pin', [], [], [], $body);
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
