<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use AspectMock\Test;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\PasswordValidationApi;
use RidiPay\Partner\Application\Dto\RegisterPartnerDto;
use RidiPay\Tests\TestUtil;
use RidiPay\Partner\Application\Service\PartnerAppService;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use RidiPay\Transaction\Domain\TransactionStatusConstant;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\PaymentMethodConstant;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class OneTimePaymentTest extends ControllerTestCase
{
    /** @var Client */
    private static $client;

    /** @var RegisterPartnerDto */
    private static $partner;

    /** @var int */
    private static $u_idx;

    /** @var string */
    private static $payment_method_id;

    /** @var string */
    private static $reservation_id;

    /** @var string */
    private static $transaction_id;

    public static function setUpBeforeClass()
    {
        self::$u_idx = TestUtil::getRandomUidx();
        UserAppService::createUser(self::$u_idx);

        self::$payment_method_id = TestUtil::createCard(self::$u_idx);
        self::$partner = PartnerAppService::registerPartner('test', 'test@12345', true);

        self::$client = self::createClient(
            [],
            [
                'HTTP_Api-Key' => self::$partner->api_key,
                'HTTP_Secret-Key' => self::$partner->secret_key
            ]
        );
        TestUtil::setUpOAuth2Doubles(self::$u_idx, TestUtil::U_ID);
        TestUtil::setUpJwtDoubles();
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownJwtDoubles();
        TestUtil::tearDownOAuth2Doubles();
    }

    public function testOneTimePaymentLifeCycleInCaseOfOnetouchPay()
    {
        UserAppService::enableOnetouchPay(self::$u_idx);

        // 결제 수단 조회
        $this->assertGetPaymentMethodsSuccessfully();

        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $amount = 10000;
        $return_url = 'https://mock.net';

        // 결제 예약
        $this->assertReservePaymentSuccessfully(
            self::$payment_method_id,
            $partner_transaction_id,
            $product_name,
            $amount,
            $return_url
        );

        // 원터치 결제 인증
        $this->assertTrue(UserAppService::isUsingOnetouchPay(self::$u_idx));
        $validation_token = TransactionAppService::generateValidationToken(self::$reservation_id);

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
    }

    public function testOneTimePaymentLifeCycleCaseInCaseOfPinValidation()
    {
        $pin = '123456';
        UserAppService::updatePin(self::$u_idx, $pin);
        UserAppService::disableOnetouchPay(self::$u_idx);

        // 결제 수단 조회
        $this->assertGetPaymentMethodsSuccessfully();

        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $amount = 10000;
        $return_url = 'https://mock.net';

        // 결제 예약
        $this->assertReservePaymentSuccessfully(
            self::$payment_method_id,
            $partner_transaction_id,
            $product_name,
            $amount,
            $return_url
        );

        // 결제 비밀번호 인증
        UserAppService::validatePin(self::$u_idx, $pin);
        $validation_token = TransactionAppService::generateValidationToken(self::$reservation_id);

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
    }

    public function testOneTimePaymentLifeCycleCaseInCaseOfPasswordValidation()
    {
        Test::double(PasswordValidationApi::class, ['isPasswordMatched' => true]);

        // 결제 수단 조회
        $this->assertGetPaymentMethodsSuccessfully();

        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $amount = 100000;
        $return_url = 'https://mock.net';

        // 결제 예약
        $this->assertReservePaymentSuccessfully(
            self::$payment_method_id,
            $partner_transaction_id,
            $product_name,
            $amount,
            $return_url
        );

        // 계정 비밀번호 인증
        UserAppService::validatePassword(self::$u_idx, 'test', 'abcde@12345');
        $validation_token = TransactionAppService::generateValidationToken(self::$reservation_id);

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

        Test::clean(PasswordValidationApi::class);
    }

    private function assertGetPaymentMethodsSuccessfully()
    {
        // 결제 수단 조회
        self::$client->request(Request::METHOD_GET, '/users/' . self::$u_idx . '/payment-methods');
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $expected_response = json_encode([
            'cards' => [
                [
                    'iin' => substr(TestUtil::CARD['CARD_NUMBER'], 0, 6),
                    'issuer_name' => '신한카드',
                    'payment_method_id' => self::$payment_method_id
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
        $client = self::createClientWithOAuth2AccessToken(
            [],
            [
                'HTTP_Api-Key' => self::$partner->api_key,
                'HTTP_Secret-Key' => self::$partner->secret_key
            ]
        );
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
        $this->assertSame(self::$payment_method_id, $response->payment_method_id);
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
        // 결제 승인
        self::$client->request(Request::METHOD_POST, "/payments/{$transaction_id}/approve");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame($product_name, $response->product_name);
        $this->assertSame($amount, $response->amount);

        // 결제 상태 확인
        self::$client->request(Request::METHOD_GET, "/payments/{$transaction_id}/status");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame(self::$payment_method_id, $response->payment_method_id);
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
        // 결제 취소
        self::$client->request(Request::METHOD_POST, "/payments/{$transaction_id}/cancel");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame($product_name, $response->product_name);
        $this->assertSame($amount, $response->amount);

        // 결제 상태 확인
        self::$client->request(Request::METHOD_GET, "/payments/{$transaction_id}/status");
        $this->assertSame(Response::HTTP_OK, self::$client->getResponse()->getStatusCode());
        $response = json_decode(self::$client->getResponse()->getContent());
        $this->assertSame($transaction_id, $response->transaction_id);
        $this->assertSame($partner_transaction_id, $response->partner_transaction_id);
        $this->assertSame(self::$payment_method_id, $response->payment_method_id);
        $this->assertSame(PaymentMethodConstant::TYPE_CARD, $response->payment_method_type);
        $this->assertSame(TransactionStatusConstant::CANCELED, $response->status);
        $this->assertSame($product_name, $response->product_name);
        $this->assertSame($amount, $response->amount);
    }
}
