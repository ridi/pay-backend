<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\User\Dto\CardDto;
use RidiPay\User\Exception\AlreadyCardAddedException;
use RidiPay\User\Repository\PaymentMethodRepository;
use RidiPay\User\Service\CardService;
use RidiPay\User\Service\PaymentMethodService;

class CardTest extends TestCase
{
    private const TAX_ID = '940101'; // 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리

    private const CARDS = [
        [
            'CARD_NUMBER' => '5164531234567890',
            'CARD_EXPIRATION_DATE' => '2511',
            'CARD_PASSWORD' => '12'
        ],
        [
            'CARD_NUMBER' => '5107371234567890',
            'CARD_EXPIRATION_DATE' => '2511',
            'CARD_PASSWORD' => '12'
        ]
    ];

    private $u_idx;

    public static function setUpBeforeClass()
    {
        TestUtil::setUpDatabaseDoubles();
    }

    protected function setUp()
    {
        $this->u_idx = TestUtil::getRandomUidx();
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownDatabaseDoubles();
    }

    public function testAddCard()
    {
        $payment_method_id = CardService::addCard(
            $this->u_idx,
            self::CARDS[0]['CARD_NUMBER'],
            self::CARDS[0]['CARD_EXPIRATION_DATE'],
            self::CARDS[0]['CARD_PASSWORD'],
            self::TAX_ID,
            true
        );
        $payment_method = PaymentMethodRepository::getRepository()->findOneByUuid(Uuid::fromString($payment_method_id));

        $card_for_one_time_payment = $payment_method->getCardForOneTimePayment();
        $this->assertNotNull($card_for_one_time_payment);
        $this->assertTrue($card_for_one_time_payment->isSameCard(self::CARDS[0]['CARD_NUMBER']));

        $card_for_billing_payment = $payment_method->getCardForBillingPayment();
        $this->assertNotNull($card_for_billing_payment);
        $this->assertTrue($card_for_billing_payment->isSameCard(self::CARDS[0]['CARD_NUMBER']));
    }

    public function testPreventAddingCardIfUserHasCard()
    {
        CardService::addCard(
            $this->u_idx,
            self::CARDS[0]['CARD_NUMBER'],
            self::CARDS[0]['CARD_EXPIRATION_DATE'],
            self::CARDS[0]['CARD_PASSWORD'],
            self::TAX_ID,
            true
        );

        $this->expectException(AlreadyCardAddedException::class);
        CardService::addCard(
            $this->u_idx,
            self::CARDS[1]['CARD_NUMBER'],
            self::CARDS[1]['CARD_EXPIRATION_DATE'],
            self::CARDS[1]['CARD_PASSWORD'],
            self::TAX_ID,
            true
        );
    }

    public function testDeleteCard()
    {
        $payment_method_id = CardService::addCard(
            $this->u_idx,
            self::CARDS[0]['CARD_NUMBER'],
            self::CARDS[0]['CARD_EXPIRATION_DATE'],
            self::CARDS[0]['CARD_PASSWORD'],
            self::TAX_ID,
            true
        );

        CardService::deleteCard($this->u_idx, $payment_method_id);

        $this->assertNull(self::getCard($this->u_idx));
    }

    /**
     * @param int $u_idx
     * @return CardDto
     */
    private static function getCard(int $u_idx): ?CardDto
    {
        $payment_methods = PaymentMethodService::getAvailablePaymentMethods($u_idx);

        return !empty($payment_methods->cards) ? $payment_methods->cards[0] : null;
    }
}
