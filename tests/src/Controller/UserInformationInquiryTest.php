<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserInformationInquiryTest extends ControllerTestCase
{
    /** @var Client */
    private static $client;

    /** @var string */
    private static $payment_method_id;

    public static function setUpBeforeClass()
    {
        $u_idx = TestUtil::getRandomUidx();
        UserAppService::createUser($u_idx);
        UserAppService::updatePin($u_idx, '123456');
        UserAppService::enableOnetouchPay($u_idx);

        self::$payment_method_id = TestUtil::createCard($u_idx);

        self::$client = self::createClientWithOAuth2AccessToken();
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownOAuth2Doubles();
    }

    public function testUserInformationInquiry()
    {
        self::$client->request(Request::METHOD_GET, '/me');
        $expected_response = json_encode([
            'payment_methods' => [
                'cards' => [
                    [
                        'iin' => substr(TestUtil::CARD['CARD_NUMBER'], 0, 6),
                        'issuer_name' => '신한카드',
                        'color' => '000000',
                        'logo_image_url' => '',
                        'subscriptions' => [],
                        'payment_method_id' => self::$payment_method_id
                    ]
                ]
            ],
            'has_pin' => true,
            'is_using_onetouch_pay' => true
        ]);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $this->assertSame($expected_response, self::$client->getResponse()->getContent());
    }
}
