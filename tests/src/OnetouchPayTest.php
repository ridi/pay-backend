<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use PHPUnit\Framework\TestCase;
use RidiPay\User\Exception\OnetouchPaySettingException;
use RidiPay\User\Service\UserService;

class OnetouchPayTest extends TestCase
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

    public function testEnableOnetouchPayWhenAddingFirstPaymentMethod()
    {
        UserService::enableOnetouchPay($this->u_idx);
        $this->assertTrue(UserService::isUsingOnetouchPay($this->u_idx));
    }

    public function testDisableOnetouchPayWhenAddingFirstPaymentMethodAndNotHavingPin()
    {
        $this->expectException(OnetouchPaySettingException::class);
        UserService::disableOnetouchPay($this->u_idx);
    }

    public function testDisableOnetouchPayWhenAddingFirstPaymentMethodAndHavingPin()
    {
        UserService::updatePin($this->u_idx, PinTest::getValidPin());

        UserService::disableOnetouchPay($this->u_idx);
        $this->assertFalse(UserService::isUsingOnetouchPay($this->u_idx));
    }

    public function testEnableOnetouchPay()
    {
        UserService::updatePin($this->u_idx, PinTest::getValidPin());
        UserService::disableOnetouchPay($this->u_idx);

        UserService::enableOnetouchPay($this->u_idx);
        $this->assertTrue(UserService::isUsingOnetouchPay($this->u_idx));
    }

    public function testDisableOnetouchPay()
    {
        UserService::updatePin($this->u_idx, PinTest::getValidPin());
        UserService::enableOnetouchPay($this->u_idx);

        UserService::disableOnetouchPay($this->u_idx);
        $this->assertFalse(UserService::isUsingOnetouchPay($this->u_idx));
    }
}
