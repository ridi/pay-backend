<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use AspectMock\Test as test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use RidiPay\Library\PasswordValidationApi;
use RidiPay\Transaction\Constant\TransactionConstant;
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

    /** @var string */
    private static $transaction_id;

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
        $this->assertCreateTransactionSuccessfully(
            $reservation_id,
            $partner_transaction_id,
            $return_url,
            $product_name,
            $amount
        );

        // 결제 승인
        $this->assertApproveTransactionSuccessfully(
            $partner_transaction_id,
            $product_name,
            $amount
        );

        // 결제 취소
        $this->assertCancelTransactionSuccessfully(
            $partner_transaction_id,
            $product_name,
            $amount
        );
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
        $this->assertCreateTransactionSuccessfully(
            $reservation_id,
            $partner_transaction_id,
            $return_url,
            $product_name,
            $amount
        );

        // 결제 승인
        $this->assertApproveTransactionSuccessfully(
            $partner_transaction_id,
            $product_name,
            $amount
        );

        // 결제 취소
        $this->assertCancelTransactionSuccessfully(
            $partner_transaction_id,
            $product_name,
            $amount
        );
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
        $this->assertCreateTransactionSuccessfully(
            $reservation_id,
            $partner_transaction_id,
            $return_url,
            $product_name,
            $amount
        );

        // 결제 승인
        $this->assertApproveTransactionSuccessfully(
            $partner_transaction_id,
            $product_name,
            $amount
        );

        // 결제 취소
        $this->assertCancelTransactionSuccessfully(
            $partner_transaction_id,
            $product_name,
            $amount
        );

        test::clean(PasswordValidationApi::class);
    }

    /**
     * @param string $reservation_id
     * @param string $partner_transaction_id
     * @param string $return_url
     * @param string $product_name
     * @param int $amount
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \RidiPay\Transaction\Exception\UnauthorizedPartnerException
     */
    private function assertCreateTransactionSuccessfully(
        string $reservation_id,
        string $partner_transaction_id,
        string $return_url,
        string $product_name,
        int $amount
    ) {
        // 결제 Transaction 생성
        $create_transaction_dto = TransactionService::createTransaction($reservation_id);
        $this->assertSame($partner_transaction_id, $create_transaction_dto->partner_transaction_id);
        $this->assertSame($return_url, $create_transaction_dto->return_url);

        // 상태 조회
        $transaction_status = TransactionService::getTransactionStatus(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            $create_transaction_dto->transaction_id
        );
        $this->assertSame($create_transaction_dto->transaction_id, $transaction_status->transaction_id);
        $this->assertSame($partner_transaction_id, $transaction_status->partner_transaction_id);
        $this->assertSame(TransactionConstant::STATUS_RESERVED, $transaction_status->status);
        $this->assertSame($product_name, $transaction_status->product_name);
        $this->assertSame($amount, $transaction_status->amount);

        self::$transaction_id = $create_transaction_dto->transaction_id;
    }

    /**
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \RidiPay\Transaction\Exception\UnauthorizedPartnerException
     * @throws \RidiPay\Transaction\Exception\UnsupportedPgException
     * @throws \Throwable
     */
    private function assertApproveTransactionSuccessfully(
        string $partner_transaction_id,
        string $product_name,
        int $amount
    ) {
        // 결제 승인
        $approve_transaction_dto = TransactionService::approveTransaction(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            self::$transaction_id,
            true
        );
        $this->assertSame(self::$transaction_id, $approve_transaction_dto->transaction_id);
        $this->assertSame($partner_transaction_id, $approve_transaction_dto->partner_transaction_id);
        $this->assertSame($product_name, $approve_transaction_dto->product_name);
        $this->assertSame($amount, $approve_transaction_dto->amount);

        // 상태 조회
        $transaction_status = TransactionService::getTransactionStatus(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            $approve_transaction_dto->transaction_id
        );
        $this->assertSame(TransactionConstant::STATUS_APPROVED, $transaction_status->status);
        $this->assertSame($approve_transaction_dto->approved_at, $transaction_status->approved_at);
        $this->assertNotNull($transaction_status->card_receipt_url);
    }

    /**
     * @param string $partner_transaction_id
     * @param string $product_name
     * @param int $amount
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \RidiPay\Transaction\Exception\UnauthorizedPartnerException
     * @throws \RidiPay\Transaction\Exception\UnsupportedPgException
     * @throws \Throwable
     */
    private function assertCancelTransactionSuccessfully(
        string $partner_transaction_id,
        string $product_name,
        int $amount
    ) {
        // 결제 취소
        $cancel_transaction_dto = TransactionService::cancelTransaction(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            self::$transaction_id,
            true
        );
        $this->assertSame(self::$transaction_id, $cancel_transaction_dto->transaction_id);
        $this->assertSame($partner_transaction_id, $cancel_transaction_dto->partner_transaction_id);
        $this->assertSame($product_name, $cancel_transaction_dto->product_name);
        $this->assertSame($amount, $cancel_transaction_dto->amount);

        // 상태 조회
        $transaction_status = TransactionService::getTransactionStatus(
            self::$partner->api_key,
            self::$partner->secret_key,
            $this->u_idx,
            self::$transaction_id
        );
        $this->assertSame(TransactionConstant::STATUS_CANCELED, $transaction_status->status);
        $this->assertSame($cancel_transaction_dto->canceled_at, $transaction_status->canceled_at);
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
            '940101'
        );

        return $payment_method_id;
    }
}
