<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use AspectMock\Test;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\Pg\Kcp\BatchOrderResponse;
use RidiPay\Library\Pg\Kcp\CancelTransactionResponse;
use RidiPay\Library\Pg\Kcp\Client as KcpClient;
use RidiPay\Library\Pg\Kcp\Response as KcpResponse;
use RidiPay\Partner\Application\Dto\PartnerRegistrationDto;
use RidiPay\Tests\TestUtil;
use RidiPay\Partner\Application\Service\PartnerAppService;
use RidiPay\Transaction\Domain\Repository\TransactionRepository;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BillingPaymentTest extends ControllerTestCase
{
    private const PIN = '123456';

    /** @var Client */
    private static $client;

    /** @var PartnerRegistrationDto */
    private static $partner;

    /** @var int */
    private static $u_idx;

    /** @var string */
    private static $payment_method_id;

    /** @var string */
    private static $reservation_id;

    /** @var string */
    private static $subscription_id;

    public static function setUpBeforeClass()
    {
        self::$u_idx = TestUtil::getRandomUidx();
        self::$payment_method_id = TestUtil::registerCard(self::$u_idx, self::PIN);
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
        TestUtil::setUpOAuth2Doubles(self::$u_idx, TestUtil::U_ID);

        $product_name = 'mock';
        $amount = 10000;
        $return_url = 'https://mock.net';

        // 구독 예약
        $this->assertReserveSubscriptionSuccessfully(
            self::$payment_method_id,
            $product_name,
            $return_url
        );

        // 구독 예약 정보 조회
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_GET, '/payments/subscriptions/' . self::$reservation_id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $subscription_reservation_response = json_decode($client->getResponse()->getContent());
        $is_pin_validation_required = $subscription_reservation_response->is_pin_validation_required;

        if ($is_pin_validation_required) {
            // 결제 비밀번호 확인
            $pin_validation_body = json_encode(['pin' => self::PIN]);
            $client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $pin_validation_body);
            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
            $pin_validation_response = json_decode($client->getResponse()->getContent());
            $validation_token = $pin_validation_response->validation_token;
        } else {
            $validation_token = $subscription_reservation_response->validation_token;
        }

        // 구독 등록
        $body = json_encode(['validation_token' => $validation_token]);
        $client->request(Request::METHOD_POST, '/payments/subscriptions/' . self::$reservation_id, [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $subscription_response = json_decode($client->getResponse()->getContent());
        $return_url = $subscription_response->return_url;
        $query_strings = [];
        parse_str(parse_url($return_url)['query'], $query_strings);
        $subscription_id = $query_strings['subscription_id'];

        // 구독 확인
        self::$client->request(Request::METHOD_GET, "/payments/subscriptions/{$subscription_id}/status");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $subscription_status_response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($subscription_id, $subscription_status_response->subscription_id);
        $this->assertSame(self::$u_idx, $subscription_status_response->u_idx);
        $this->assertSame($product_name, $subscription_status_response->product_name);
        $this->assertSame(self::$payment_method_id, $subscription_status_response->payment_method_id);

        // 구독 결제 승인
        $partner_transaction_id = Uuid::uuid4()->toString();
        $this->assertPaySubscriptionSuccessfully(
            $subscription_id,
            $partner_transaction_id,
            $product_name,
            $amount,
            Uuid::uuid4()->toString()
        );

        // 구독 해지
        self::$client->request(Request::METHOD_DELETE, "/payments/subscriptions/{$subscription_id}");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        // 구독 재개
        self::$client->request(Request::METHOD_PUT, "/payments/subscriptions/{$subscription_id}/resume");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        // 구독 결제 승인
        $partner_transaction_id = Uuid::uuid4()->toString();
        $this->assertPaySubscriptionSuccessfully(
            $subscription_id,
            $partner_transaction_id,
            $product_name,
            $amount,
            Uuid::uuid4()->toString()
        );
    }

    public function testExceptionHandlingInCaseOfUnauthorizedPartner()
    {
        TestUtil::setUpOAuth2Doubles(self::$u_idx, TestUtil::U_ID);

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
        $return_url = 'https://mock.net';

        // Unauthorized subscription reservation
        $body = json_encode([
            'payment_method_id' => self::$payment_method_id,
            'product_name' => $product_name,
            'return_url' => $return_url
        ]);
        $unauthorized_client->request(Request::METHOD_POST, '/payments/subscriptions/reserve', [], [], [], $body);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized subscription reservation
        $this->assertReserveSubscriptionSuccessfully(
            self::$payment_method_id,
            $product_name,
            $return_url
        );

        // 구독 등록
        $validation_token = UserAppService::generateValidationToken(self::$u_idx);
        $this->assertSubscriptionSuccessfully($validation_token);

        // Unauthorized getting subscription status
        $unauthorized_client->request(
            Request::METHOD_GET,
            '/payments/subscriptions/' . self::$subscription_id . '/status'
        );
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized getting subscription status
        self::$client->request(
            Request::METHOD_GET,
            '/payments/subscriptions/' . self::$subscription_id . '/status'
        );
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

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
            '/payments/subscriptions/' . self::$subscription_id . '/pay',
            [],
            [],
            [],
            $subscription_payment_body
        );
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized subscription payment
        $kcp_client = Test::double(
            KcpClient::class,
            [
                'batchOrder' => new BatchOrderResponse([
                    'code' => KcpResponse::OK,
                    'message' => '',
                    'tno' => uniqid(),
                    'order_no' => $partner_transaction_id,
                    'amount' => $amount,
                    'approval_time' => (new \DateTime())->format('YmdHis'),
                ]),
            ]
        );
        self::$client->request(
            Request::METHOD_POST,
            '/payments/subscriptions/' . self::$subscription_id . '/pay',
            [],
            [],
            [],
            $subscription_payment_body
        );
        Test::clean($kcp_client);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        // Unauthorized unsubscription
        $unauthorized_client->request(Request::METHOD_DELETE, '/payments/subscriptions/' . self::$subscription_id);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized unsubscription
        self::$client->request(Request::METHOD_DELETE, '/payments/subscriptions/' . self::$subscription_id);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        // Unauthorized subscription resumption
        $unauthorized_client->request(Request::METHOD_PUT, '/payments/subscriptions/' . self::$subscription_id . '/resume');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized subscription resumption
        self::$client->request(Request::METHOD_PUT, '/payments/subscriptions/' . self::$subscription_id . '/resume');
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
    }

    /**
     * 동일한 invoice_id에 대해서 중복 결제 승인이 발생하지 않는지 확인
     * @throws \Exception
     */
    public function testPaymentIdempotency()
    {
        TestUtil::setUpOAuth2Doubles(self::$u_idx, TestUtil::U_ID);

        $product_name = 'mock';
        $amount = 10000;
        $return_url = 'https://mock.net';

        // 구독 예약
        $body = json_encode([
            'payment_method_id' => self::$payment_method_id,
            'product_name' => $product_name,
            'return_url' => $return_url
        ]);
        self::$client->request(Request::METHOD_POST, '/payments/subscriptions/reserve', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $reservation_id = json_decode(self::$client->getResponse()->getContent())->reservation_id;

        // 구독 예약 정보 조회
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_GET, '/payments/subscriptions/' . $reservation_id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $subscription_reservation_response = json_decode($client->getResponse()->getContent());

        if ($subscription_reservation_response->is_pin_validation_required) {
            // 결제 비밀번호 확인
            $pin_validation_body = json_encode(['pin' => self::PIN]);
            $client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $pin_validation_body);
            $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
            $pin_validation_response = json_decode($client->getResponse()->getContent());
            $validation_token = $pin_validation_response->validation_token;
        } else {
            $validation_token = $subscription_reservation_response->validation_token;
        }

        // 구독 등록
        $body = json_encode(['validation_token' => $validation_token]);
        $client->request(Request::METHOD_POST, "/payments/subscriptions/{$reservation_id}", [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $return_url = json_decode($client->getResponse()->getContent())->return_url;
        $query_strings = [];
        parse_str(parse_url($return_url)['query'], $query_strings);
        $subscription_id = $query_strings['subscription_id'];

        // 결제 승인(결제 발생 O)
        $invoice_id = Uuid::uuid4()->toString();
        $response_content = $this->assertPaySubscriptionSuccessfully(
            $subscription_id,
            Uuid::uuid4()->toString(),
            $product_name,
            $amount,
            $invoice_id
        );
        $transaction_id = $response_content->transaction_id;
        $partner_transaction_id = $response_content->partner_transaction_id;
        $pg_transaction_id = TransactionRepository::getRepository()->findOneByUuid(
            Uuid::fromString($transaction_id)
        )->getPgTransactionId();

        // 가맹점 오류로 인한 결제 취소
        $this->assertTransactionCancellationSuccessfully($transaction_id, $partner_transaction_id, $amount);

        // 동일한 invoice_id로 결제 승인 retry(결제 발생 O)
        $response_content = $this->assertPaySubscriptionSuccessfully(
            $subscription_id,
            Uuid::uuid4()->toString(),
            $product_name,
            $amount,
            $invoice_id
        );
        $this->assertNotSame($transaction_id, $response_content->transaction_id);
        $this->assertNotSame($partner_transaction_id, $response_content->partner_transaction_id);
        $this->assertNotSame(
            $pg_transaction_id,
            TransactionRepository::getRepository()->findOneByUuid(
                Uuid::fromString($response_content->transaction_id)
            )->getPgTransactionId()
        );
        $transaction_id = $response_content->transaction_id;
        $partner_transaction_id = $response_content->partner_transaction_id;
        $pg_transaction_id = TransactionRepository::getRepository()->findOneByUuid(
            Uuid::fromString($transaction_id)
        )->getPgTransactionId();

        // 동일한 invoice_id로 결제 승인 retry(결제 발생 X)
        $body = json_encode([
            'partner_transaction_id' => Uuid::uuid4()->toString(),
            'amount' => $amount,
            'buyer_id' => TestUtil::U_ID,
            'buyer_name' => '테스트',
            'buyer_email' => 'payment-test@ridi.com',
            'invoice_id' => $invoice_id
        ]);
        self::$client->request(Request::METHOD_POST, "/payments/subscriptions/{$subscription_id}/pay", [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response_content = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response_content->transaction_id);
        $this->assertSame($partner_transaction_id, $response_content->partner_transaction_id);
        $this->assertSame(
            $pg_transaction_id,
            TransactionRepository::getRepository()->findOneByUuid(
                Uuid::fromString($transaction_id)
            )->getPgTransactionId()
        );

        // 결제 취소
        $this->assertTransactionCancellationSuccessfully($transaction_id, $partner_transaction_id, $amount);

        // 결제 취소 retry
        $this->assertTransactionCancellationSuccessfully($transaction_id, $partner_transaction_id, $amount);

        // 다른 invoice id로 결제 승인(결제 발생 O)
        $response_content = $this->assertPaySubscriptionSuccessfully(
            $subscription_id,
            Uuid::uuid4()->toString(),
            $product_name,
            $amount,
            Uuid::uuid4()->toString()
        );
        $this->assertNotSame($transaction_id, $response_content->transaction_id);
        $this->assertNotSame($partner_transaction_id, $response_content->partner_transaction_id);
        $this->assertNotSame(
            $pg_transaction_id,
            TransactionRepository::getRepository()->findOneByUuid(
                Uuid::fromString($response_content->transaction_id)
            )->getPgTransactionId()
        );

        $transaction_id = $response_content->transaction_id;
        $partner_transaction_id = $response_content->partner_transaction_id;

        // 결제 취소
        $this->assertTransactionCancellationSuccessfully($transaction_id, $partner_transaction_id, $amount);

        // 결제 취소 retry
        $this->assertTransactionCancellationSuccessfully($transaction_id, $partner_transaction_id, $amount);
    }

    /**
     * @param string $payment_method_id
     * @param string $product_name
     * @param string $return_url
     */
    private function assertReserveSubscriptionSuccessfully(
        string $payment_method_id,
        string $product_name,
        string $return_url
    ) {
        $body = json_encode([
            'payment_method_id' => $payment_method_id,
            'product_name' => $product_name,
            'return_url' => $return_url
        ]);
        self::$client->request(Request::METHOD_POST, '/payments/subscriptions/reserve', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        self::$reservation_id = json_decode(self::$client->getResponse()->getContent())->reservation_id;
    }

    /**
     * @param string $validation_token
     */
    private function assertSubscriptionSuccessfully(string $validation_token)
    {
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $body = json_encode(['validation_token' => $validation_token]);
        $client->request(Request::METHOD_POST, '/payments/subscriptions/' . self::$reservation_id, [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        $return_url = json_decode($client->getResponse()->getContent())->return_url;
        $query_strings = [];
        parse_str(parse_url($return_url)['query'], $query_strings);
        self::$subscription_id = $query_strings['subscription_id'];
    }

    /**
     * @param string $subscription_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @param string $invoice_id
     * @return \stdClass
     * @throws \Exception
     */
    private function assertPaySubscriptionSuccessfully(
        string $subscription_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount,
        string $invoice_id
    ): \stdClass {
        $client = Test::double(
            KcpClient::class,
            [
                'batchOrder' => new BatchOrderResponse([
                    'code' => KcpResponse::OK,
                    'message' => '',
                    'tno' => uniqid(),
                    'order_no' => $partner_transaction_id,
                    'amount' => $amount,
                    'approval_time' => (new \DateTime())->format('YmdHis'),
                ]),
            ]
        );
        $body = json_encode([
            'partner_transaction_id' => $partner_transaction_id,
            'amount' => $amount,
            'buyer_id' => TestUtil::U_ID,
            'buyer_name' => '테스트',
            'buyer_email' => 'payment-test@ridi.com',
            'invoice_id' => $invoice_id
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
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame($product_name, $response->product_name);
        $this->assertSame($amount, $response->amount);
        Test::clean($client);

        return $response;
    }

    /**
     * @param string $transaction_id
     * @param string $partner_transaction_id
     * @param int $amount
     * @throws \Exception
     */
    private function assertTransactionCancellationSuccessfully(
        string $transaction_id,
        string $partner_transaction_id,
        int $amount
    ) {
        $client = Test::double(
            KcpClient::class,
            [
                'cancelTransaction' => new CancelTransactionResponse([
                    'code' => KcpResponse::OK,
                    'message' => '',
                    'tno' => uniqid(),
                    'order_no' => $partner_transaction_id,
                    'amount' => $amount,
                    'cancel_time' => (new \DateTime())->format('YmdHis'),
                ]),
            ]
        );
        self::$client->request(Request::METHOD_POST, "/payments/{$transaction_id}/cancel");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        Test::clean($client);
    }
}
