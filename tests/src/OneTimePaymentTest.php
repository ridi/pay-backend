<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use AspectMock\Test as test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\PasswordValidationApi;
use RidiPay\Transaction\Dto\PartnerDto;
use RidiPay\Transaction\Service\PartnerService;
use RidiPay\Transaction\Service\TransactionService;
use RidiPay\User\Service\CardService;
use RidiPay\User\Service\UserService;

class OneTimePaymentTest extends TestCase
{
    /** @var int */
    private $u_idx;

    /** @var PartnerDto */
    private static $partner;

    public static function setUpBeforeClass()
    {
        TestUtil::setUpDatabaseDoubles();

        self::$partner = PartnerService::registerPartner('test', 'test@12345', true);
    }

    protected function setUp()
    {
        $this->u_idx = TestUtil::getRandomUidx();
        UserService::createUserIfNotExists($this->u_idx);
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownDatabaseDoubles();
    }

    public function testOneTimePaymentLifeCycleInCaseOfOnetouchPay()
    {
        UserService::enableOnetouchPay($this->u_idx);

        $payment_method_id = $this->createCard();
        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $amount = 10000;
        $return_url = 'https://mock.net';

        // 결제 예약
        $reservation_id = TransactionService::reserveTransaction(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            $payment_method_id,
            $partner_transaction_id,
            $product_name,
            $amount,
            $return_url
        );

        // 인증
        $this->assertTrue(UserService::isUsingOnetouchPay($this->u_idx));

        // 결제 Transaction 생성
        $create_transaction_dto = TransactionService::createTransaction($reservation_id);
        $this->assertSame($create_transaction_dto->partner_transaction_id, $partner_transaction_id);
        $this->assertSame($create_transaction_dto->return_url, $return_url);

        // 결제 승인
        $approve_transaction_dto = TransactionService::approveTransaction(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            $create_transaction_dto->transaction_id,
            true
        );
        $this->assertSame($approve_transaction_dto->partner_transaction_id, $partner_transaction_id);
        $this->assertSame($approve_transaction_dto->transaction_id, $create_transaction_dto->transaction_id);
        $this->assertSame($approve_transaction_dto->product_name, $product_name);
        $this->assertSame($approve_transaction_dto->amount, $amount);
    }

    public function testOneTimePaymentLifeCycleCaseInCaseOfPinValidation()
    {
        $pin = '123456';
        UserService::updatePin($this->u_idx, $pin);
        UserService::disableOnetouchPay($this->u_idx);

        $payment_method_id = $this->createCard();
        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $amount = 50000;
        $return_url = 'https://mock.net';

        // 결제 예약
        $reservation_id = TransactionService::reserveTransaction(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            $payment_method_id,
            $partner_transaction_id,
            $product_name,
            $amount,
            $return_url
        );

        // 인증
        UserService::validatePin($this->u_idx, $pin);

        // 결제 Transaction 생성
        $create_transaction_dto = TransactionService::createTransaction($reservation_id);
        $this->assertSame($create_transaction_dto->partner_transaction_id, $partner_transaction_id);
        $this->assertSame($create_transaction_dto->return_url, $return_url);

        // 결제 승인
        $approve_transaction_dto = TransactionService::approveTransaction(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            $create_transaction_dto->transaction_id,
            true
        );
        $this->assertSame($approve_transaction_dto->partner_transaction_id, $partner_transaction_id);
        $this->assertSame($approve_transaction_dto->transaction_id, $create_transaction_dto->transaction_id);
        $this->assertSame($approve_transaction_dto->product_name, $product_name);
        $this->assertSame($approve_transaction_dto->amount, $amount);
    }

    public function testOneTimePaymentLifeCycleCaseInCaseOfPasswordValidation()
    {
        test::double(PasswordValidationApi::class, ['isPasswordMatched' => true]);

        $payment_method_id = $this->createCard();
        $partner_transaction_id = Uuid::uuid4()->toString();
        $product_name = 'mock';
        $amount = 100000;
        $return_url = 'https://mock.net';

        // 결제 예약
        $reservation_id = TransactionService::reserveTransaction(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            $payment_method_id,
            $partner_transaction_id,
            $product_name,
            $amount,
            $return_url
        );

        // 인증
        UserService::validatePassword($this->u_idx, 'abcde@12345');

        // 결제 Transaction 생성
        $create_transaction_dto = TransactionService::createTransaction($reservation_id);
        $this->assertSame($create_transaction_dto->partner_transaction_id, $partner_transaction_id);
        $this->assertSame($create_transaction_dto->return_url, $return_url);

        // 결제 승인
        $approve_transaction_dto = TransactionService::approveTransaction(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            $create_transaction_dto->transaction_id,
            true
        );
        $this->assertSame($approve_transaction_dto->partner_transaction_id, $partner_transaction_id);
        $this->assertSame($approve_transaction_dto->transaction_id, $create_transaction_dto->transaction_id);
        $this->assertSame($approve_transaction_dto->product_name, $product_name);
        $this->assertSame($approve_transaction_dto->amount, $amount);

        test::clean(PasswordValidationApi::class);
    }

    /**
     * @return string
     * @throws \RidiPay\User\Exception\AlreadyCardAddedException
     * @throws \Throwable
     */
    private function createCard(): string
    {
        $payment_method_id = CardService::addCard(
            $this->u_idx,
            '5164531234567890',
            '2511',
            '11',
            '940101',
            true
        );

        return $payment_method_id;
    }
}
