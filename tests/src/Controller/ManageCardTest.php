<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use Ramsey\Uuid\Uuid;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

class ManageCardTest extends ControllerTestCase
{
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
    private const TAX_ID = '940101'; // 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리

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

    public function testManageCard()
    {
        // 카드 최초 등록
        $body = json_encode([
            'card_number' => self::CARD_A['CARD_NUMBER'],
            'card_expiration_date' => self::CARD_A['CARD_EXPIRATION_DATE'],
            'card_password' => self::CARD_A['CARD_PASSWORD'],
            'tax_id' => self::TAX_ID
        ]);
        self::$client->request('POST', '/users/' . TestUtil::U_ID . '/cards', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods(self::$u_idx);
        $this->assertNotEmpty($payment_methods->cards);

        $card = $payment_methods->cards[0];
        $payment_method = PaymentMethodRepository::getRepository()
            ->findOneByUuid(Uuid::fromString($card->payment_method_id));

        $card_for_one_time_payment = $payment_method->getCardForOneTimePayment();
        $this->assertNotNull($card_for_one_time_payment);
        $this->assertTrue($card_for_one_time_payment->isSameCard(self::CARD_A['CARD_NUMBER']));

        $card_for_billing_payment = $payment_method->getCardForBillingPayment();
        $this->assertNotNull($card_for_billing_payment);
        $this->assertTrue($card_for_billing_payment->isSameCard(self::CARD_A['CARD_NUMBER']));

        // 카드 추가 등록 시도
        $body = json_encode([
            'card_number' => self::CARD_B['CARD_NUMBER'],
            'card_expiration_date' => self::CARD_B['CARD_EXPIRATION_DATE'],
            'card_password' => self::CARD_B['CARD_PASSWORD'],
            'tax_id' => self::TAX_ID
        ]);
        self::$client->request('POST', '/users/' . TestUtil::U_ID . '/cards', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());

        // 카드 삭제
        self::$client->request('DELETE', '/users/' . TestUtil::U_ID . '/cards/' . $card->payment_method_id);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods(self::$u_idx);
        $this->assertEmpty($payment_methods->cards);
    }
}
