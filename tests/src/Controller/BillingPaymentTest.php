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
                'HTTP_Secret-Key' => self::$partner->secret_key,
                'CONTENT_TYPE' => 'application/json'
            ]
        );
    }

    public function testBillingPaymentLifeCycle()
    {
        $product_name = 'mock';
        $amount = 10000;

        // 정기 결제 등록
        $body = json_encode([
            'payment_method_id' => self::$payment_method_id,
            'product_name' => $product_name
        ]);
        self::$client->request(Request::METHOD_POST, '/payments/subscriptions', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        $response_content = json_decode(self::$client->getResponse()->getContent());
        $subscription_id = $response_content->subscription_id;

        // 정기 결제 승인
        $body = json_encode([
            'partner_transaction_id' => Uuid::uuid4()->toString(),
            'amount' => $amount,
            'buyer_id' => TestUtil::U_ID,
            'buyer_name' => '테스트',
            'buyer_email' => 'payment-test@ridi.com',
            'invoice_id' => Uuid::uuid4()->toString()
        ]);
        self::$client->request(
            Request::METHOD_POST,
            "/payments/subscriptions/{$subscription_id}/pay",
            [],
            [],
            [],
            $body
        );
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        // 정기 결제 해지
        self::$client->request(Request::METHOD_DELETE, "/payments/subscriptions/{$subscription_id}");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        // 정기 결제 재개
        self::$client->request(Request::METHOD_PUT, "/payments/subscriptions/{$subscription_id}/resume");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        // 정기 결제 승인
        $body = json_encode([
            'partner_transaction_id' => Uuid::uuid4()->toString(),
            'amount' => $amount,
            'buyer_id' => TestUtil::U_ID,
            'buyer_name' => '테스트',
            'buyer_email' => 'payment-test@ridi.com',
            'invoice_id' => Uuid::uuid4()->toString()
        ]);
        self::$client->request(
            Request::METHOD_POST,
            "/payments/subscriptions/{$subscription_id}/pay",
            [],
            [],
            [],
            $body
        );
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
    }

    public function testExceptionHandlingInCaseOfUnauthorizedPartner()
    {
        $unauthorized_client = self::createClient(
            [],
            [
                'HTTP_Api-Key' => self::$partner->api_key,
                'HTTP_Secret-Key' => 'invalid_secret_key',
                'CONTENT_TYPE' => 'application/json'
            ]
        );

        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $amount = 10000;

        $subscription_body = json_encode([
            'payment_method_id' => self::$payment_method_id,
            'product_name' => $product_name
        ]);
        // Unauthorized subscription
        $unauthorized_client->request(Request::METHOD_POST, '/payments/subscriptions', [], [], [], $subscription_body);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized subscription
        self::$client->request(Request::METHOD_POST, '/payments/subscriptions', [], [], [], $subscription_body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        $subscription_response_content = json_decode(self::$client->getResponse()->getContent());
        $subscription_id = $subscription_response_content->subscription_id;

        $subscription_payment_body = json_encode([
            'partner_transaction_id' => $partner_transaction_id,
            'amount' => $amount,
            'buyer_id' => TestUtil::U_ID,
            'buyer_name' => '테스트',
            'buyer_email' => 'payment-test@ridi.com',
            'invoice_id' => Uuid::uuid4()->toString()
        ]);

        // Unauthorized subscription payment
        $unauthorized_client->request(
            Request::METHOD_POST,
            "/payments/subscriptions/{$subscription_id}/pay",
            [],
            [],
            [],
            $subscription_payment_body
        );
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized subscription payment
        self::$client->request(
            Request::METHOD_POST,
            "/payments/subscriptions/{$subscription_id}/pay",
            [],
            [],
            [],
            $subscription_payment_body
        );
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        // Unauthorized unsubscription
        $unauthorized_client->request(Request::METHOD_DELETE, "/payments/subscriptions/{$subscription_id}");
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized unsubscription
        self::$client->request(Request::METHOD_DELETE, "/payments/subscriptions/{$subscription_id}");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        // Unauthorized subscription resumption
        $unauthorized_client->request(Request::METHOD_PUT, "/payments/subscriptions/{$subscription_id}/resume");
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized subscription resumption
        self::$client->request(Request::METHOD_PUT, "/payments/subscriptions/{$subscription_id}/resume");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
    }
}
