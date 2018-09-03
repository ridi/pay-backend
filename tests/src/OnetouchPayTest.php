<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use PHPUnit\Framework\TestCase;
use RidiPay\User\Domain\Exception\OnetouchPaySettingException;
use RidiPay\User\Application\Service\UserAppService;

class OnetouchPayTest extends TestCase
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

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownDatabaseDoubles();
    }

    public function testEnableOnetouchPayWhenAddingFirstPaymentMethod()
    {
        UserAppService::enableOnetouchPay($this->u_idx);
        $this->assertTrue(UserAppService::isUsingOnetouchPay($this->u_idx));
    }

    public function testDisableOnetouchPayWhenAddingFirstPaymentMethodAndNotHavingPin()
    {
        $this->expectException(OnetouchPaySettingException::class);
        UserAppService::disableOnetouchPay($this->u_idx);
    }

    public function testDisableOnetouchPayWhenAddingFirstPaymentMethodAndHavingPin()
    {
        UserAppService::updatePin($this->u_idx, PinTest::getValidPin());

        UserAppService::disableOnetouchPay($this->u_idx);
        $this->assertFalse(UserAppService::isUsingOnetouchPay($this->u_idx));
    }

    public function testEnableOnetouchPay()
    {
        UserAppService::updatePin($this->u_idx, PinTest::getValidPin());
        UserAppService::disableOnetouchPay($this->u_idx);

        UserAppService::enableOnetouchPay($this->u_idx);
        $this->assertTrue(UserAppService::isUsingOnetouchPay($this->u_idx));
    }

    public function testDisableOnetouchPay()
    {
        UserAppService::updatePin($this->u_idx, PinTest::getValidPin());
        UserAppService::enableOnetouchPay($this->u_idx);

        UserAppService::disableOnetouchPay($this->u_idx);
        $this->assertFalse(UserAppService::isUsingOnetouchPay($this->u_idx));
    }
}
