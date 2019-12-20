<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use AspectMock\Test;
use Ramsey\Uuid\Uuid;
use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Partner\Application\Service\PartnerAppService;
use RidiPay\Partner\Domain\Repository\PartnerRepository;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Tests\TestUtil;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Repository\SubscriptionRepository;
use RidiPay\Transaction\Domain\Service\RidiCashAutoChargeSubscriptionOptoutManager;
use RidiPay\Transaction\Domain\Service\RidiSelectSubscriptionOptoutManager;
use RidiPay\Transaction\Domain\SubscriptionConstant;
use RidiPay\User\Application\Service\EmailSender;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
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
     * 결제 수단 등록 테스트
     *
     * @throws AuthorizationException
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public function testRegisterCard()
    {
        $u_idx = TestUtil::getRandomUidx();
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $body = json_encode([
            'card_number' => self::CARD_A['CARD_NUMBER'],
            'card_expiration_date' => self::CARD_A['CARD_EXPIRATION_DATE'],
            'card_password' => self::CARD_A['CARD_PASSWORD'],
            'tax_id' => self::TAX_ID
        ]);
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_POST, '/me/cards', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $response_content = json_decode($client->getResponse()->getContent());
        $body = json_encode([
            'pin' => self::PIN,
            'validation_token' => $response_content->validation_token
        ]);
        $client->request(Request::METHOD_POST, '/me/pin', [], [], [], $body);

        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
        $this->assertCount(1, $payment_methods->cards);

        $card = $payment_methods->cards[0];
        $payment_method = PaymentMethodRepository::getRepository()
            ->findOneByUuid(Uuid::fromString($card->payment_method_id));
        $card_for_one_time_payment = $payment_method->getCardForOneTimePayment();
        $this->assertNotNull($card_for_one_time_payment);
        $card_for_billing_payment = $payment_method->getCardForBillingPayment();
        $this->assertNotNull($card_for_billing_payment);

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * 결제 수단 삭제 테스트
     *
     * @dataProvider deleteCardDataProvider
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
        Test::double(RidiCashAutoChargeSubscriptionOptoutManager::class, ['optout' => null]);
        Test::double(RidiSelectSubscriptionOptoutManager::class, ['optout' => null]);

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
        }

        TestUtil::tearDownOAuth2Doubles();

        Test::clean(RidiSelectSubscriptionOptoutManager::class);
        Test::clean(RidiCashAutoChargeSubscriptionOptoutManager::class);
    }

    /**
     * 결제 수단 변경 테스트
     *
     * @dataProvider changeCardDataProvider
     *
     * @param int $u_idx
     * @param string $payment_method_id
     * @param string[] $expected_subscription_ids
     * @throws AuthorizationException
     * @throws UnsupportedPaymentMethodException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\ORMException
     */
    public function testChangeCard(
        int $u_idx,
        string $payment_method_id,
        array $expected_subscription_ids
    ) {
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $body = json_encode([
            'card_number' => self::CARD_B['CARD_NUMBER'],
            'card_expiration_date' => self::CARD_B['CARD_EXPIRATION_DATE'],
            'card_password' => self::CARD_B['CARD_PASSWORD'],
            'tax_id' => self::TAX_ID
        ]);
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_POST, '/me/cards', [], [], [], $body);
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $response_content = json_decode($client->getResponse()->getContent());
        $body = json_encode([
            'pin' => self::PIN,
            'validation_token' => $response_content->validation_token
        ]);
        $client->request(Request::METHOD_POST, '/me/pin', [], [], [], $body);

        // 기존 결제 수단 삭제 여부 확인
        $previous_payment_method = PaymentMethodRepository::getRepository()
            ->findOneByUuid(Uuid::fromString($payment_method_id));
        $this->assertTrue($previous_payment_method->isDeleted());

        // 신규 결제 수단 등록 여부 확인
        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
        $this->assertCount(1, $payment_methods->cards);

        $card = $payment_methods->cards[0];
        $payment_method = PaymentMethodRepository::getRepository()
            ->findOneByUuid(Uuid::fromString($card->payment_method_id));
        $card_for_one_time_payment = $payment_method->getCardForOneTimePayment();
        $this->assertNotNull($card_for_one_time_payment);
        $card_for_billing_payment = $payment_method->getCardForBillingPayment();
        $this->assertNotNull($card_for_billing_payment);

        // 기존 카드로부터 신규 카드로 구독 이전 여부 확인
        $this->assertMigrateSubscriptionSuccessfully($expected_subscription_ids, $card->payment_method_id);

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * @param int[] $expected_subscription_ids
     * @param string $new_payment_method_id
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private function assertMigrateSubscriptionSuccessfully(
        array $expected_subscription_ids,
        string $new_payment_method_id
    ) {
        $subscriptions_of_new_payment_method = SubscriptionRepository::getRepository()->findActiveOnesByPaymentMethodId(
            PaymentMethodRepository::getRepository()->findOneByUuid(
                Uuid::fromString($new_payment_method_id)
            )->getId()
        );
        usort($subscriptions_of_new_payment_method, function (SubscriptionEntity $a, SubscriptionEntity $b) {
            return $a->getId() > $b->getId();
        });

        $this->assertSameSize($expected_subscription_ids, $subscriptions_of_new_payment_method);
        $subscription_count = count($expected_subscription_ids);

        for ($i = 0; $i < $subscription_count; $i++) {
            $this->assertSame(
                $expected_subscription_ids[$i],
                $subscriptions_of_new_payment_method[$i]->getId()
            );
        }
    }

    /**
     * @return array
     * @throws CardRegistrationException
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public function deleteCardDataProvider(): array
    {
        $user_indices = [];
        for ($i = 0; $i < 6; $i++) {
            $user_indices[] = TestUtil::getRandomUidx();
        }
        $partner = PartnerAppService::registerPartner('delete-card', 'test@12345', true);

        $payment_method_id_of_normal_user = TestUtil::registerCard(
            $user_indices[0],
            '123456',
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );

        $payment_method_id_of_normal_user_with_ridi_cash_auto_charge = TestUtil::registerCard(
            $user_indices[1],
            '123456',
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );
        $subscription = new SubscriptionEntity(
            PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_id_of_normal_user_with_ridi_cash_auto_charge),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDI_CASH_AUTO_CHARGE
        );
        SubscriptionRepository::getRepository()->save($subscription);

        $payment_method_id_of_normal_user_with_ridiselect = TestUtil::registerCard(
            $user_indices[2],
            '123456',
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );
        $subscription = new SubscriptionEntity(
            PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_id_of_normal_user_with_ridiselect),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDISELECT
        );
        SubscriptionRepository::getRepository()->save($subscription);

        $payment_method_id_of_leaved_user = TestUtil::registerCard(
            $user_indices[3],
            '123456',
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );
        UserAppService::deleteUser($user_indices[3]);

        // $user_indices[4]: NOT_FOUND_USER

        $payment_method_id_of_other_user = TestUtil::registerCard(
            $user_indices[5],
            '123456',
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );

        return [
            [
                $user_indices[0],
                $payment_method_id_of_normal_user,
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[1],
                $payment_method_id_of_normal_user_with_ridi_cash_auto_charge,
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[2],
                $payment_method_id_of_normal_user_with_ridiselect,
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[3],
                $payment_method_id_of_leaved_user,
                Response::HTTP_FORBIDDEN,
                UserErrorCodeConstant::LEAVED_USER
            ],
            [
                $user_indices[4],
                Uuid::uuid4()->toString(),
                Response::HTTP_NOT_FOUND,
                UserErrorCodeConstant::NOT_FOUND_USER
            ],
            [
                $user_indices[0],
                Uuid::uuid4()->toString(),
                Response::HTTP_NOT_FOUND,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD
            ],
            [
                $user_indices[0],
                $payment_method_id_of_other_user,
                Response::HTTP_NOT_FOUND,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD
            ]
        ];
    }

    /**
     * @return array
     * @throws CardRegistrationException
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public function changeCardDataProvider(): array
    {
        $cases = [];

        $user_indices = [];
        for ($i = 0; $i < 4; $i++) {
            $user_indices[] = TestUtil::getRandomUidx();
        }
        $partner = PartnerAppService::registerPartner('change-card', 'test@12345', true);

        $payment_method_id = TestUtil::registerCard(
            $user_indices[0],
            '123456',
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );
        $cases[] = [
            $user_indices[0],
            $payment_method_id,
            []
        ];

        $payment_method_id_with_ridi_cash_auto_charge_subscription = TestUtil::registerCard(
            $user_indices[1],
            '123456',
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );
        $subscription = new SubscriptionEntity(
            PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_id_with_ridi_cash_auto_charge_subscription),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDI_CASH_AUTO_CHARGE
        );
        SubscriptionRepository::getRepository()->save($subscription);
        $cases[] = [
            $user_indices[1],
            $payment_method_id_with_ridi_cash_auto_charge_subscription,
            [$subscription->getId()]
        ];

        $payment_method_id_with_ridiselect_subscription = TestUtil::registerCard(
            $user_indices[2],
            '123456',
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );
        $subscription = new SubscriptionEntity(
            PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_id_with_ridiselect_subscription),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDISELECT
        );
        SubscriptionRepository::getRepository()->save($subscription);
        $cases[] = [
            $user_indices[2],
            $payment_method_id_with_ridiselect_subscription,
            [$subscription->getId()]
        ];

        $payment_method_id_with_multiple_subscriptions = TestUtil::registerCard(
            $user_indices[3],
            '123456',
            self::CARD_A['CARD_NUMBER'],
            self::CARD_A['CARD_EXPIRATION_DATE'],
            self::CARD_A['CARD_PASSWORD'],
            self::TAX_ID
        );
        $ridi_cash_auto_charge_subscription = new SubscriptionEntity(
            PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_id_with_multiple_subscriptions),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDI_CASH_AUTO_CHARGE
        );
        SubscriptionRepository::getRepository()->save($ridi_cash_auto_charge_subscription);
        $ridiselect_subscription = new SubscriptionEntity(
            PaymentMethodAppService::getPaymentMethodIdByUuid($payment_method_id_with_multiple_subscriptions),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDISELECT
        );
        SubscriptionRepository::getRepository()->save($ridiselect_subscription);
        $cases[] = [
            $user_indices[3],
            $payment_method_id_with_multiple_subscriptions,
            [
                $ridi_cash_auto_charge_subscription->getId(),
                $ridiselect_subscription->getId()
            ]
        ];

        return $cases;
    }
}
