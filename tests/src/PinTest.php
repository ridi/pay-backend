<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use PHPUnit\Framework\TestCase;
use RidiPay\User\Domain\Exception\PasswordEntryBlockedException;
use RidiPay\User\Domain\Exception\UnmatchedPinException;
use RidiPay\User\Domain\Exception\WrongPinException;
use RidiPay\User\Domain\Service\PinEntryAbuseBlockPolicy;
use RidiPay\User\Application\Service\UserAppService;

class PinTest extends TestCase
{
    /** @var int */
    private $u_idx;

    protected function setUp()
    {
        TestUtil::setUpDatabaseDoubles();

        $this->u_idx = TestUtil::getRandomUidx();
        UserAppService::createUserIfNotExists($this->u_idx);
    }

    protected function tearDown()
    {
        TestUtil::tearDownDatabaseDoubles();
    }

    public function testUpdateValidPin()
    {
        $this->expectNotToPerformAssertions();

        $pin = self::getValidPin();
        UserAppService::updatePin($this->u_idx, $pin);
    }

    public function testPreventUpdatingInvalidPinWithShortLength()
    {
        $this->expectException(WrongPinException::class);

        $pin = self::getInvalidPinWithShortLength();
        UserAppService::updatePin($this->u_idx, $pin);
    }

    public function testPreventUpdatingInvalidPinIncludingUnsupportedCharacters()
    {
        $this->expectException(WrongPinException::class);

        $pin = self::getInvalidPinIncludingUnsupportedCharacters();
        UserAppService::updatePin($this->u_idx, $pin);
    }

    public function testEnterPinCorrectly()
    {
        $pin = self::getValidPin();
        UserAppService::updatePin($this->u_idx, $pin);

        $this->expectNotToPerformAssertions();
        UserAppService::validatePin($this->u_idx, $pin);
    }

    public function testEnterPinIncorrectly()
    {
        $pin = self::getValidPin();
        UserAppService::updatePin($this->u_idx, $pin);

        $this->expectException(UnmatchedPinException::class);
        UserAppService::validatePin($this->u_idx, 'abcdef');
    }

    public function testPinEntryAbuseBlock()
    {
        $pin = self::getValidPin();
        UserAppService::updatePin($this->u_idx, $pin);

        $policy = new PinEntryAbuseBlockPolicy();
        for ($try_count = 0; $try_count < $policy->getBlockThreshold() - 1; $try_count++) {
            $this->expectException(UnmatchedPinException::class);
            UserAppService::validatePin($this->u_idx, self::getInvalidPinIncludingUnsupportedCharacters());
        }

        // Block
        $this->expectException(PasswordEntryBlockedException::class);
        UserAppService::validatePin($this->u_idx, self::getInvalidPinIncludingUnsupportedCharacters());

        // Block 이후 시도
        $this->expectException(PasswordEntryBlockedException::class);
        UserAppService::validatePin($this->u_idx, self::getInvalidPinIncludingUnsupportedCharacters());
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
