<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use PHPUnit\Framework\TestCase;
use RidiPay\User\Exception\UnmatchedPinException;
use RidiPay\User\Exception\WrongPinException;
use RidiPay\User\Service\UserService;

class PinTest extends TestCase
{
    /** @var int */
    private $u_idx;

    protected function setUp()
    {
        TestUtil::setUpDatabaseDoubles();

        $this->u_idx = TestUtil::getRandomUidx();
        UserService::createUserIfNotExists($this->u_idx);
    }

    protected function tearDown()
    {
        TestUtil::tearDownDatabaseDoubles();
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownDatabaseDoubles();
    }

    public function testUpdateValidPin()
    {
        $this->expectNotToPerformAssertions();

        $pin = self::getValidPin();
        UserService::updatePin($this->u_idx, $pin);
    }

    public function testPreventUpdatingInvalidPinWithShortLength()
    {
        $this->expectException(WrongPinException::class);

        $pin = self::getInvalidPinWithShortLength();
        UserService::updatePin($this->u_idx, $pin);
    }

    public function testPreventUpdatingInvalidPinIncludingUnsupportedCharacters()
    {
        $this->expectException(WrongPinException::class);

        $pin = self::getInvalidPinIncludingUnsupportedCharacters();
        UserService::updatePin($this->u_idx, $pin);
    }

    public function testEnterPinCorrectly()
    {
        $this->expectNotToPerformAssertions();

        $pin = self::getValidPin();
        UserService::updatePin($this->u_idx, $pin);

        UserService::validatePin($this->u_idx, $pin);
    }

    public function testEnterPinIncorrectly()
    {
        $this->expectException(UnmatchedPinException::class);

        $pin = self::getValidPin();
        UserService::updatePin($this->u_idx, $pin);

        UserService::validatePin($this->u_idx, 'abcdef');
    }

    /**
     * @return string
     */
    public static function getValidPin(): string
    {
        return substr(str_shuffle('0123456789'), 0, 6);
    }

    /**
     * @return string
     */
    public static function getInvalidPinWithShortLength(): string
    {
        return substr(str_shuffle('0123456789'), 0, 4);
    }

    /**
     * @return string
     */
    public static function getInvalidPinIncludingUnsupportedCharacters(): string
    {
        $supported_characters = substr(str_shuffle('0123456789'), 0, 4);
        $unsupported_characters = substr(str_shuffle('abcdeefhji'), 0, 2);

        return str_shuffle($supported_characters . $unsupported_characters);
    }
}
