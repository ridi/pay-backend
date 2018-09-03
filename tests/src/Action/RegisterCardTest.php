<?php
declare(strict_types=1);

namespace RidiPay\Tests\Action;

use AspectMock\Test as test;
use Ramsey\Uuid\Uuid;
use Ridibooks\OAuth2\Constant\AccessTokenConstant;
use RidiPay\Library\OAuth2\User\DefaultUserProvider;
use RidiPay\Library\OAuth2\User\User;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

class RegisterCardTest extends WebTestCase
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

    public function testRegisterCard()
    {
        // 카드 최초 등록
        $body = json_encode([
            'card_number' => self::CARD_A['CARD_NUMBER'],
            'card_expiration_date' => self::CARD_A['CARD_EXPIRATION_DATE'],
            'card_password' => self::CARD_A['CARD_PASSWORD'],
            'tax_id' => self::TAX_ID
        ]);
        self::$client->request('POST', '/users/' . self::U_ID . '/cards', [], [], [], $body);
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
        self::$client->request('POST', '/users/' . self::U_ID . '/cards', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, self::$client->getResponse()->getStatusCode());
    }
}
