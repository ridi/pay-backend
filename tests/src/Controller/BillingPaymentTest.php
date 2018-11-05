<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use Ramsey\Uuid\Uuid;
use RidiPay\Partner\Application\Dto\PartnerRegistrationDto;
use RidiPay\Tests\TestUtil;
use RidiPay\Partner\Application\Service\PartnerAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingPaymentTest extends ControllerTestCase
{
    /** @var Client */
    private static $client;

    /** @var PartnerRegistrationDto */
    private static $partner;

    /** @var int */
    private static $u_idx;

    /** @var string */
    private static $payment_method_id;

    public static function setUpBeforeClass()
    {
        self::$u_idx = TestUtil::getRandomUidx();
        self::$payment_method_id = TestUtil::registerCard(
            self::$u_idx,
            '123456',
            true,
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );
        self::$partner = PartnerAppService::registerPartner('billing-payment-test', 'test@12345', true);

        self::$client = self::createClient(
            [],
            [
                'HTTP_Api-Key' => self::$partner->api_key,
                'HTTP_Secret-Key' => self::$partner->secret_key
            ]
        );
    }

    public function testBillingPaymentLifeCycle()
    {
        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $amount = 10000;

        // 정기 결제 등록
        $body = json_encode([
            'payment_method_id' => self::$payment_method_id,
            'product_name' => $product_name,
            'amount' => $amount
        ]);
        self::$client->request(Request::METHOD_POST, '/payments/subscriptions', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        $response_content = json_decode(self::$client->getResponse()->getContent());

        // 정기 결제 승인
        $body = json_encode([
            'partner_transaction_id' => $partner_transaction_id
        ]);
        self::$client->request(
            Request::METHOD_POST, "/payments/subscriptions/{$response_content->subscription_id}/pay",
            [],
            [],
            [],
            $body
        );
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
    }
}
