<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use AspectMock\Test;
use Ramsey\Uuid\Uuid;
use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\EmailSender;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Exception\CardAlreadyExistsException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ManageCardTest extends ControllerTestCase
{
    private const CARD_A = [
        'CARD_NUMBER' => '5164531234567890',
        'CARD_EXPIRATION_DATE' => '2511',
        'CARD_PASSWORD' => '12'
    ];
    private const CARD_B = [
        'CARD_NUMBER' => '5107371234567890',
        'CARD_EXPIRATION_DATE' => '2511',
        'CARD_PASSWORD' => '12'
    ];
    private const TAX_ID = '940101'; // 개인: 생년월일(YYMMDD) / 법인: 사업자 등록 번호 10자리

    private const PIN = '123456';

    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass()
    {
        Test::double(EmailSender::class, ['send' => null]);
    }

    public static function tearDownAfterClass()
    {
        Test::clean(EmailSender::class);
    }

    /**
     * @dataProvider userAndCardProvider
     *
     * @param int $u_idx
     * @param string $card_number
     * @param string $card_expiration_date
     * @param string $card_password
     * @param string $tax_id
     * @param int $http_status_code
     * @param null|string $error_code
     * @throws AuthorizationException
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public function testRegisterCard(
        int $u_idx,
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id,
        int $http_status_code,
        ?string $error_code
    ) {
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $body = json_encode([
            'card_number' => $card_number,
            'card_expiration_date' => $card_expiration_date,
            'card_password' => $card_password,
            'tax_id' => $tax_id
        ]);
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_POST, '/me/cards', [], [], [], $body);
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());

        $response_content = json_decode($client->getResponse()->getContent());
        if (isset($response_content->code)) {
            $this->assertSame($error_code, $response_content->code);
        } else {
            $body = json_encode([
                'pin' => self::PIN,
                'validation_token' => $response_content->validation_token
            ]);
            $client->request(Request::METHOD_POST, '/me/pin', [], [], [], $body);

            $body = json_encode([
                'enable_onetouch_pay' => true,
                'validation_token' => $response_content->validation_token
            ]);
            $client->request(Request::METHOD_POST, '/me/onetouch', [], [], [], $body);

            $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
            if (!empty($payment_methods->cards)) {
                $card = $payment_methods->cards[0];
                $payment_method = PaymentMethodRepository::getRepository()
                    ->findOneByUuid(Uuid::fromString($card->payment_method_id));

                $card_for_one_time_payment = $payment_method->getCardForOneTimePayment();
                $this->assertNotNull($card_for_one_time_payment);

                $card_for_billing_payment = $payment_method->getCardForBillingPayment();
                $this->assertNotNull($card_for_billing_payment);
            }
        }

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * @dataProvider userAndPaymentMethodIdProvider
     *
     * @param int $u_idx
     * @param string $payment_method_id
     * @param int $http_status_code
     * @param null|string $error_code
     * @throws AuthorizationException
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function testDeleteCard(
        int $u_idx,
        string $payment_method_id,
        int $http_status_code,
        ?string $error_code
    ) {
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_DELETE, "/me/cards/{$payment_method_id}");
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());

        $response_content = json_decode($client->getResponse()->getContent());
        if (isset($response_content->code)) {
            $this->assertSame($error_code, $response_content->code);
        } else {
            $user = UserAppService::getUserInformation($u_idx);
            $this->assertEmpty($user->payment_methods->cards);
            $this->assertFalse($user->has_pin);
            $this->assertNull($user->is_using_onetouch_pay);
        }

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * @return array
     * @throws CardAlreadyExistsException
     * @throws CardRegistrationException
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public function userAndCardProvider(): array
    {
        $user_indices = [];
        for ($i = 0; $i < 2; $i++) {
            $user_indices[] = TestUtil::getRandomUidx();
        }

        TestUtil::registerCard(
            $user_indices[1],
            self::PIN,
            true,
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );

        return [
            [
                $user_indices[0],
                self::CARD_A['CARD_NUMBER'],
                self::CARD_A['CARD_EXPIRATION_DATE'],
                self::CARD_A['CARD_PASSWORD'],
                self::TAX_ID,
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[1],
                self::CARD_B['CARD_NUMBER'],
                self::CARD_B['CARD_EXPIRATION_DATE'],
                self::CARD_B['CARD_PASSWORD'],
                self::TAX_ID,
                Response::HTTP_FORBIDDEN,
                UserErrorCodeConstant::CARD_ALREADY_EXISTS
            ]
        ];
    }

    /**
     * @return array
     * @throws CardAlreadyExistsException
     * @throws CardRegistrationException
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public function userAndPaymentMethodIdProvider(): array
    {
        $user_indices = [];
        for ($i = 0; $i < 3; $i++) {
            $user_indices[] = TestUtil::getRandomUidx();
        }

        $payment_method_id_of_normal_user = TestUtil::registerCard(
            $user_indices[0],
            '123456',
            true,
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );

        $payment_method_id_of_leaved_user = TestUtil::registerCard(
            $user_indices[1],
            '123456',
            true,
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );
        UserAppService::deleteUser($user_indices[1]);

        return [
            [
                $user_indices[0],
                $payment_method_id_of_normal_user,
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[1],
                $payment_method_id_of_leaved_user,
                Response::HTTP_FORBIDDEN,
                UserErrorCodeConstant::LEAVED_USER
            ],
            [
                $user_indices[2],
                Uuid::uuid4()->toString(),
                Response::HTTP_NOT_FOUND,
                UserErrorCodeConstant::NOT_FOUND_USER
            ],
            [
                $user_indices[0],
                Uuid::uuid4()->toString(),
                Response::HTTP_NOT_FOUND,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD
            ]
        ];
    }
}
