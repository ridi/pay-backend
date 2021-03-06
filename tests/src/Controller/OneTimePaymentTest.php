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
use RidiPay\Transaction\Domain\TransactionStatusConstant;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\PaymentMethodConstant;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OneTimePaymentTest extends ControllerTestCase
{
    private const PIN = '123456';

    /** @var Client */
    private static $client;

    /** @var PartnerRegistrationDto */
    private static $partner;

    /** @var int */
    private static $u_idx;

    /** @var CardEntity */
    private static $card;

    /** @var string */
    private static $reservation_id;

    /** @var string */
    private static $transaction_id;

    public static function setUpBeforeClass()
    {
        self::$partner = PartnerAppService::registerPartner('one-time-payment', 'test@12345', true);
        self::$client = self::createClient(
            [],
            [
                'HTTP_Api-Key' => self::$partner->api_key,
                'HTTP_Secret-Key' => self::$partner->secret_key,
                'CONTENT_TYPE' => 'application/json'
            ]
        );
        TestUtil::setUpJwtDoubles();
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownJwtDoubles();
    }

    protected function setUp()
    {
        self::$u_idx = TestUtil::getRandomUidx();
        self::$card = TestUtil::registerCard(self::$u_idx, self::PIN);
        TestUtil::setUpOAuth2Doubles(self::$u_idx, TestUtil::U_ID);
    }

    public function testOneTimePaymentLifeCycle()
    {
        // 결제 수단 조회
        $this->assertGetPaymentMethodsSuccessfully();

        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $return_url = 'https://mock.net';
        $amount = 10000;

        // 결제 예약
        $this->assertReservePaymentSuccessfully(
            self::$card->getUuid()->toString(),
            $partner_transaction_id,
            $product_name,
            $amount,
            $return_url
        );

        // 결제 예약 정보 조회
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_GET, '/payments/' . self::$reservation_id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 결제 비밀번호 확인
        $pin_validation_body = json_encode(['pin' => self::PIN]);
        $client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $pin_validation_body);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $pin_validation_response = json_decode($client->getResponse()->getContent());
        $validation_token = $pin_validation_response->validation_token;

        // 결제 생성
        $this->assertCreatePaymentSuccessfully(
            $validation_token,
            self::$reservation_id,
            $partner_transaction_id,
            $product_name,
            $amount
        );

        // 결제 승인
        $this->assertApprovePaymentSuccessfully(self::$transaction_id, $partner_transaction_id, $product_name, $amount);

        // 결제 취소
        $this->assertCancelPaymentSuccessfully(self::$transaction_id, $partner_transaction_id, $product_name, $amount);

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * 동일한 transaction_id에 대해서 중복 결제 승인이 발생하지 않는지 확인
     */
    public function testPaymentIdempotency()
    {
        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $amount = 10000;
        $return_url = 'https://mock.net';

        // 결제 예약
        $this->assertReservePaymentSuccessfully(
            self::$card->getUuid()->toString(),
            $partner_transaction_id,
            $product_name,
            $amount,
            $return_url
        );

        // 결제 예약 정보 조회
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_GET, '/payments/' . self::$reservation_id);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // 결제 비밀번호 확인
        $pin_validation_body = json_encode(['pin' => self::PIN]);
        $client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $pin_validation_body);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $pin_validation_response = json_decode($client->getResponse()->getContent());
        $validation_token = $pin_validation_response->validation_token;

        // 결제 생성
        $this->assertCreatePaymentSuccessfully(
            $validation_token,
            self::$reservation_id,
            $partner_transaction_id,
            $product_name,
            $amount
        );

        // 결제 승인(결제 발생 O)
        $this->assertApprovePaymentSuccessfully(self::$transaction_id, $partner_transaction_id, $product_name, $amount);
        
        $pg_transaction_id = TransactionRepository::getRepository()->findOneByUuid(
            Uuid::fromString(self::$transaction_id)
        )->getPgTransactionId();

        // 결제 승인 retry(결제 발생 X)
        $this->assertApprovePaymentSuccessfully(self::$transaction_id, $partner_transaction_id, $product_name, $amount);
        $this->assertSame(
            $pg_transaction_id,
            TransactionRepository::getRepository()->findOneByUuid(
                Uuid::fromString(self::$transaction_id)
            )->getPgTransactionId()
        );

        // 가맹점 오류로 인한 결제 취소
        $this->assertCancelPaymentSuccessfully(self::$transaction_id, $partner_transaction_id, $product_name, $amount);
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame(self::$transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);

        // 결제 취소 retry
        self::$client->request(Request::METHOD_POST, '/payments/' . self::$transaction_id . '/cancel');
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
        $return_url = 'https://mock.net';

        // Unauthorized payment reservation
        $body = json_encode([
            'payment_method_id' => self::$card->getUuid()->toString(),
            'partner_transaction_id' => $partner_transaction_id,
            'product_name' => $product_name,
            'amount' => $amount,
            'return_url' => $return_url
        ]);
        $unauthorized_client->request(Request::METHOD_POST, '/payments/reserve', [], [], [], $body);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized payment reservation
        $this->assertReservePaymentSuccessfully(
            self::$card->getUuid()->toString(),
            $partner_transaction_id,
            $product_name,
            $amount,
            $return_url
        );

        // 결제 생성
        $validation_token = UserAppService::generateValidationToken(self::$u_idx);
        $this->assertCreatePaymentSuccessfully(
            $validation_token,
            self::$reservation_id,
            $partner_transaction_id,
            $product_name,
            $amount
        );

        // Unauthorized payment approval
        $body = json_encode([
            'buyer_id' => TestUtil::U_ID,
            'buyer_name' => '테스트',
            'buyer_email' => 'payment-test@ridi.com'
        ]);
        $unauthorized_client->request(Request::METHOD_POST, '/payments/' . self::$transaction_id . '/approve', [], [], [], $body);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized payment approval
        $this->assertApprovePaymentSuccessfully(self::$transaction_id, $partner_transaction_id, $product_name, $amount);

        // Unauthorized getting payment status
        $unauthorized_client->request(Request::METHOD_GET, '/payments/' . self::$transaction_id . '/status');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Unauthorized payment cancellation
        $unauthorized_client->request(Request::METHOD_POST, '/payments/' . self::$transaction_id . '/cancel');
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $unauthorized_client->getResponse()->getStatusCode());

        // Authorized payment cancellation
        $this->assertCancelPaymentSuccessfully(self::$transaction_id, $partner_transaction_id, $product_name, $amount);
    }

    private function assertGetPaymentMethodsSuccessfully()
    {
        // 결제 수단 조회
        self::$client->request(Request::METHOD_GET, '/users/' . self::$u_idx . '/payment-methods');
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $expected_response = json_encode([
            'cards' => [
                [
                    'payment_method_id' => self::$card->getUuid()->toString(),
                    'iin' => substr(TestUtil::CARD['CARD_NUMBER'], 0, 6),
                    'issuer_name' => 'KB국민카드',
                    'color' => '#000000',
                    'logo_image_url' => '',
                    'subscriptions' => []
                ]
            ]
        ]);
        $this->assertSame($expected_response, self::$client->getResponse()->getContent());
    }

    /**
     * @param string $payment_method_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @param string $return_url
     */
    private function assertReservePaymentSuccessfully(
        string $payment_method_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount,
        string $return_url
    ): void {
        // 결제 예약
        $body = json_encode([
            'payment_method_id' => $payment_method_id,
            'partner_transaction_id' => $partner_transaction_id,
            'product_name' => $product_name,
            'amount' => $amount,
            'return_url' => $return_url
        ]);
        self::$client->request(Request::METHOD_POST, '/payments/reserve', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());

        self::$reservation_id = json_decode(self::$client->getResponse()->getContent())->reservation_id;
    }

    /**
     * @param string $validation_token
     * @param string $reservation_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     */
    private function assertCreatePaymentSuccessfully(
        string $validation_token,
        string $reservation_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount
    ): void {
        // 결제 생성
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $body = json_encode(['validation_token' => $validation_token]);
        $client->request(Request::METHOD_POST, "/payments/${reservation_id}", [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $return_url = json_decode($client->getResponse()->getContent())->return_url;
        $query_strings = [];
        parse_str(parse_url($return_url)['query'], $query_strings);
        self::$transaction_id = $query_strings['transaction_id'];

        // 결제 상태 확인
        self::$client->request(Request::METHOD_GET, '/payments/' . self::$transaction_id . '/status');
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame(self::$transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame(self::$card->getUuid()->toString(), $response->payment_method_id);
        $this->assertSame(PaymentMethodConstant::TYPE_CARD, $response->payment_method_type);
        $this->assertSame(TransactionStatusConstant::RESERVED, $response->status);
        $this->assertSame($product_name, $response->product_name);
        $this->assertSame($amount, $response->amount);
    }

    /**
     * @param string $transaction_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     */
    private function assertApprovePaymentSuccessfully(
        string $transaction_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount
    ): void {
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
        // 결제 승인
        $body = json_encode([
            'buyer_id' => TestUtil::U_ID,
            'buyer_name' => '테스트',
            'buyer_email' => 'payment-test@ridi.com'
        ]);
        self::$client->request(Request::METHOD_POST, "/payments/{$transaction_id}/approve", [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame($product_name, $response->product_name);
        $this->assertSame($amount, $response->amount);
        Test::clean($client);

        // 결제 상태 확인
        self::$client->request(Request::METHOD_GET, "/payments/{$transaction_id}/status");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame(self::$card->getUuid()->toString(), $response->payment_method_id);
        $this->assertSame(PaymentMethodConstant::TYPE_CARD, $response->payment_method_type);
        $this->assertSame(TransactionStatusConstant::APPROVED, $response->status);
        $this->assertSame($product_name, $response->product_name);
        $this->assertSame($amount, $response->amount);
        $this->assertNotNull($amount, $response->card_receipt_url);
    }

    /**
     * @param string $transaction_id
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     */
    private function assertCancelPaymentSuccessfully(
        string $transaction_id,
        string $partner_transaction_id,
        string $product_name,
        int $amount
    ): void {
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
        // 결제 취소
        self::$client->request(Request::METHOD_POST, "/payments/{$transaction_id}/cancel");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame($product_name, $response->product_name);
        $this->assertSame($amount, $response->amount);
        Test::clean($client);

        // 결제 상태 확인
        self::$client->request(Request::METHOD_GET, "/payments/{$transaction_id}/status");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame(self::$card->getUuid()->toString(), $response->payment_method_id);
        $this->assertSame(PaymentMethodConstant::TYPE_CARD, $response->payment_method_type);
        $this->assertSame(TransactionStatusConstant::CANCELED, $response->status);
        $this->assertSame($product_name, $response->product_name);
        $this->assertSame($amount, $response->amount);
    }
}
