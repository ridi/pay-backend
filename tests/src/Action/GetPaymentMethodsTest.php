<?php
declare(strict_types=1);

namespace RidiPay\Tests\Action;

use AspectMock\Test as test;
use Ridibooks\OAuth2\Constant\AccessTokenConstant;
use RidiPay\Library\Jwt\JwtMiddleware;
use RidiPay\Library\OAuth2\User\DefaultUserProvider;
use RidiPay\Library\OAuth2\User\User;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\CardAppService;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Exception\AlreadyHadCardException;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;

class GetPaymentMethodsTest extends WebTestCase
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
        test::double(JwtMiddleware::class, ['authorize' => null]);

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
        test::clean(JwtMiddleware::class);

        TestUtil::tearDownDatabaseDoubles();
    }

    public function testGetPaymentMethods()
    {
        // 카드 등록
        $payment_method_id = self::createCard();

        self::$client->request('GET', '/users/' . self::U_ID . '/payment-methods');
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $expected_response = json_encode([
            'payment_methods' => [
                'cards' => [
                    [
                        'iin' => substr(self::CARD['CARD_NUMBER'], 0, 6),
                        'issuer_name' => 'KB국민카드',
                        'payment_method_id' => $payment_method_id
                    ]
                ]
            ]
        ]);
        $this->assertSame($expected_response, self::$client->getResponse()->getContent());
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
