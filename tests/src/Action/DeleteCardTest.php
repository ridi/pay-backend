<?php
declare(strict_types=1);

namespace RidiPay\Tests\Action;

use AspectMock\Test as test;
use Ridibooks\OAuth2\Constant\AccessTokenConstant;
use RidiPay\Library\OAuth2\User\DefaultUserProvider;
use RidiPay\Library\OAuth2\User\User;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\CardAppService;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Exception\AlreadyHadCardException;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

class DeleteCardTest extends WebTestCase
{
    // 국민카드
    private const CARD = [
        'CARD_NUMBER' => '5164531234567890',
        'CARD_EXPIRATION_DATE' => '2511',
        'CARD_PASSWORD' => '12'
    ];
    private const TAX_ID = '940101';

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

    public function testDeleteCard()
    {
        // 카드 등록
        $payment_method_id = self::createCard();

        self::$client->request('DELETE', '/users/' . self::U_ID . '/cards/' . $payment_method_id);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods(self::$u_idx);
        $this->assertEmpty($payment_methods->cards);
    }

    /**
     * @return string
     * @throws AlreadyHadCardException
     * @throws \Throwable
     */
    private static function createCard(): string
    {
        $payment_method_id = CardAppService::registerCard(
            self::$u_idx,
            self::CARD['CARD_NUMBER'],
            self::CARD['CARD_EXPIRATION_DATE'],
            self::CARD['CARD_PASSWORD'],
            self::TAX_ID
        );

        return $payment_method_id;
    }
}
