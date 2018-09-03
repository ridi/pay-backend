<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\User\Application\Dto\CardDto;
use RidiPay\User\Domain\Exception\AlreadyHadCardException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;
use RidiPay\User\Application\Service\CardAppService;
use RidiPay\User\Application\Service\PaymentMethodAppService;

class CardTest extends TestCase
{
    private const TAX_ID = '940101'; // 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리
    private const CARD_A = [
        'CARD_NUMBER' => '5164531234567890',
        'CARD_EXPIRATION_DATE' => '2511',
        'CARD_PASSWORD' => '12'
    ];
    private const CARD_B = [
        'CARD_NUMBER' => '5107371234567890',
        'CARD_EXPIRATION_DATE' => '2511',
        'CARD_PASSWORD' => '12'
    ];

    private $u_idx;

    protected function setUp()
    {
        TestUtil::setUpDatabaseDoubles();

        $this->u_idx = TestUtil::getRandomUidx();
    }

    protected function tearDown()
    {
        TestUtil::tearDownDatabaseDoubles();
    }

    public function testRegisterCard()
    {
        $payment_method_id = CardAppService::registerCard(
            $this->u_idx,
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );
        $payment_method = PaymentMethodRepository::getRepository()->findOneByUuid(Uuid::fromString($payment_method_id));

        $card_for_one_time_payment = $payment_method->getCardForOneTimePayment();
        $this->assertNotNull($card_for_one_time_payment);
        $this->assertTrue($card_for_one_time_payment->isSameCard(self::CARD_A['CARD_NUMBER']));

        $card_for_billing_payment = $payment_method->getCardForBillingPayment();
        $this->assertNotNull($card_for_billing_payment);
        $this->assertTrue($card_for_billing_payment->isSameCard(self::CARD_A['CARD_NUMBER']));
    }

    public function testPreventCardRegisterationIfUserHadCard()
    {
        CardAppService::registerCard(
            $this->u_idx,
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );

        $this->expectException(AlreadyHadCardException::class);
        CardAppService::registerCard(
            $this->u_idx,
            self::CARD_B['CARD_NUMBER'],
            self::CARD_B['CARD_EXPIRATION_DATE'],
            self::CARD_B['CARD_PASSWORD'],
            self::TAX_ID
        );
    }

    public function testDeleteCard()
    {
        $payment_method_id = CardAppService::registerCard(
            $this->u_idx,
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );

        CardAppService::deleteCard($this->u_idx, $payment_method_id);

        $this->assertNull(self::getCard($this->u_idx));
    }

    /**
     * @param int $u_idx
     * @return null|CardDto
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private static function getCard(int $u_idx): ?CardDto
    {
        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);

        return !empty($payment_methods->cards) ? $payment_methods->cards[0] : null;
    }
}
