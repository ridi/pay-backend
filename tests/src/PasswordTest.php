<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use AspectMock\Test as test;
use PHPUnit\Framework\TestCase;
use RidiPay\Library\PasswordValidationApi;
use RidiPay\User\Domain\Exception\PasswordEntryBlockedException;
use RidiPay\User\Domain\Exception\UnmatchedPasswordException;;
use RidiPay\User\Domain\Service\PasswordEntryAbuseBlockPolicy;
use RidiPay\User\Application\Service\UserAppService;

class PasswordTest extends TestCase
{
    private const VALID_PASSWORD = 'abcde@12345';
    private const INVALID_PASSWORD = '12345@abcde';

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

    public function testEnterPasswordCorrectly()
    {
        test::double(PasswordValidationApi::class, ['isPasswordMatched' => true]);

        $this->expectNotToPerformAssertions();
        UserAppService::validatePassword($this->u_idx, self::VALID_PASSWORD);

        test::clean(PasswordValidationApi::class);
    }

    public function testEnterPasswordIncorrectly()
    {
        test::double(PasswordValidationApi::class, ['isPasswordMatched' => false]);

        $this->expectException(UnmatchedPasswordException::class);
        UserAppService::validatePassword($this->u_idx, self::INVALID_PASSWORD);

        test::clean(PasswordValidationApi::class);
    }

    public function testPasswordEntryAbuseBlock()
    {
        test::double(PasswordValidationApi::class, ['isPasswordMatched' => false]);

        $policy = new PasswordEntryAbuseBlockPolicy();
        for ($try_count = 0; $try_count < $policy->getBlockThreshold() - 1; $try_count++) {
            $this->expectException(UnmatchedPasswordException::class);
            UserAppService::validatePassword($this->u_idx, self::INVALID_PASSWORD);
        }

        // Block
        $this->expectException(PasswordEntryBlockedException::class);
        UserAppService::validatePassword($this->u_idx, self::INVALID_PASSWORD);

        // Block 이후 시도
        $this->expectException(PasswordEntryBlockedException::class);
        UserAppService::validatePassword($this->u_idx, self::INVALID_PASSWORD);

        test::clean(PasswordValidationApi::class);
    }
}
