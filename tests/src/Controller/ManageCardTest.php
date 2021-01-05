<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use AspectMock\Test;
use Ramsey\Uuid\Uuid;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Pg\Kcp\BatchKeyResponse;
use RidiPay\Library\Pg\Kcp\Client;
use RidiPay\Library\Pg\Kcp\Company;
use RidiPay\Library\Pg\Kcp\Response as KcpResponse;
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
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\PaymentMethodConstant;
use RidiPay\User\Domain\Repository\CardPaymentKeyRepository;
use RidiPay\User\Domain\Repository\PaymentMethodRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ManageCardTest extends ControllerTestCase
{
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
     */
    public function testRegisterCard()
    {
        $u_idx = TestUtil::getRandomUidx();
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $kcp_client = Test::double(
            Client::class,
            [
                'requestBatchKey' => new BatchKeyResponse([
                    'code' => KcpResponse::OK,
                    'message' => '',
                    'card_code' => Company::KOOKMIN,
                    'card_name' => Company::getKoreanName(Company::KOOKMIN),
                    'batch_key' => 'abcdefghijklmnopqrstuvwxyz'
                ]),
            ]
        );
        $body = json_encode([
            'card_number' => TestUtil::CARD['CARD_NUMBER'],
            'card_expiration_date' => TestUtil::CARD['CARD_EXPIRATION_DATE'],
            'card_password' => TestUtil::CARD['CARD_PASSWORD'],
            'tax_id' => TestUtil::TAX_ID
        ]);
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_POST, '/me/cards', [], [], [], $body);
        Test::clean($kcp_client);

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $response_content = json_decode($client->getResponse()->getContent());
        $body = json_encode([
            'pin' => self::PIN,
            'validation_token' => $response_content->validation_token
        ]);
        $client->request(Request::METHOD_POST, '/me/pin', [], [], [], $body);

        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
        $this->assertCount(1, $payment_methods);
        $card = $payment_methods[0];
        $this->assertNotNull(
            CardPaymentKeyRepository::getRepository()->findOneByCardIdAndPurpose(
                $card->getId(),
                PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_ONE_TIME
            )
        );
        $this->assertNotNull(
            CardPaymentKeyRepository::getRepository()->findOneByCardIdAndPurpose(
                $card->getId(),
                PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_ONE_TIME_TAX_DEDUCTION
            )
        );
        $this->assertNotNull(
            CardPaymentKeyRepository::getRepository()->findOneByCardIdAndPurpose(
                $card->getId(),
                PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_BILLING
            )
        );

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * 결제 수단 삭제 테스트
     *
     * @dataProvider deleteCardDataProvider
     *
     * @param int $u_idx
     * @param string $payment_method_uuid
     * @param int $http_status_code
     * @param null|string $error_code
     */
    public function testDeleteCard(
        int $u_idx,
        string $payment_method_uuid,
        int $http_status_code,
        ?string $error_code
    ) {
        Test::double(RidiCashAutoChargeSubscriptionOptoutManager::class, ['optout' => null]);
        Test::double(RidiSelectSubscriptionOptoutManager::class, ['optout' => null]);

        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_DELETE, "/me/cards/{$payment_method_uuid}");
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());

        $response_content = json_decode($client->getResponse()->getContent());
        if (isset($response_content->code)) {
            $this->assertSame($error_code, $response_content->code);
        } else {
            $this->assertEmpty(PaymentMethodAppService::getAvailablePaymentMethods($u_idx));
            $this->assertFalse(UserAppService::getUser($u_idx)->hasPin());
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
     * @param string $previous_payment_method_uuid
     * @param string[] $expected_subscription_ids
     */
    public function testChangeCard(
        int $u_idx,
        string $previous_payment_method_uuid,
        array $expected_subscription_ids
    ) {
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $kcp_client = Test::double(
            Client::class,
            [
                'requestBatchKey' => new BatchKeyResponse([
                    'code' => KcpResponse::OK,
                    'message' => '',
                    'card_code' => Company::KOOKMIN,
                    'card_name' => Company::getKoreanName(Company::KOOKMIN),
                    'batch_key' => 'abcdefghijklmnopqrstuvwxyz'
                ]),
            ]
        );
        $body = json_encode([
            'card_number' => TestUtil::CARD['CARD_NUMBER'],
            'card_expiration_date' => TestUtil::CARD['CARD_EXPIRATION_DATE'],
            'card_password' => TestUtil::CARD['CARD_PASSWORD'],
            'tax_id' => TestUtil::TAX_ID
        ]);
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_POST, '/me/cards', [], [], [], $body);
        Test::clean($kcp_client);

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $response_content = json_decode($client->getResponse()->getContent());
        $body = json_encode([
            'pin' => self::PIN,
            'validation_token' => $response_content->validation_token
        ]);
        $client->request(Request::METHOD_POST, '/me/pin', [], [], [], $body);

        // 기존 결제 수단 삭제 여부 확인
        $this->assertTrue(
            PaymentMethodRepository::getRepository()->findOneByUuid(
                Uuid::fromString($previous_payment_method_uuid)
            )->isDeleted()
        );

        // 신규 결제 수단 등록 여부 확인
        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
        $this->assertCount(1, $payment_methods);

        $card = $payment_methods[0];
        $this->assertNotNull(
            CardPaymentKeyRepository::getRepository()->findOneByCardIdAndPurpose(
                $card->getId(),
                PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_ONE_TIME
            )
        );
        $this->assertNotNull(
            CardPaymentKeyRepository::getRepository()->findOneByCardIdAndPurpose(
                $card->getId(),
                PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_ONE_TIME_TAX_DEDUCTION
            )
        );
        $this->assertNotNull(
            CardPaymentKeyRepository::getRepository()->findOneByCardIdAndPurpose(
                $card->getId(),
                PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_BILLING
            )
        );

        // 기존 카드로부터 신규 카드로 구독 이전 여부 확인
        $this->assertMigrateSubscriptionSuccessfully($expected_subscription_ids, $card);

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * @param int[] $expected_subscription_ids
     * @param PaymentMethodEntity $new_payment_method
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private function assertMigrateSubscriptionSuccessfully(
        array $expected_subscription_ids,
        PaymentMethodEntity $new_payment_method
    ) {
        $subscriptions_of_new_payment_method = SubscriptionRepository::getRepository()
            ->findActiveOnesByPaymentMethodId($new_payment_method->getId());
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

        $card_of_normal_user = TestUtil::registerCard($user_indices[0], '123456');

        $card_of_normal_user_with_ridi_cash_auto_charge = TestUtil::registerCard(
            $user_indices[1],
            '123456'
        );
        $subscription = new SubscriptionEntity(
            $card_of_normal_user_with_ridi_cash_auto_charge->getId(),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDI_CASH_AUTO_CHARGE
        );
        SubscriptionRepository::getRepository()->save($subscription);

        $card_of_normal_user_with_ridiselect = TestUtil::registerCard(
            $user_indices[2],
            '123456'
        );
        $subscription = new SubscriptionEntity(
            $card_of_normal_user_with_ridiselect->getId(),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDISELECT
        );
        SubscriptionRepository::getRepository()->save($subscription);

        $card_of_leaved_user = TestUtil::registerCard(
            $user_indices[3],
            '123456'
        );
        UserAppService::deleteUser($user_indices[3]);

        // $user_indices[4]: NOT_FOUND_USER

        $card_of_other_user = TestUtil::registerCard(
            $user_indices[5],
            '123456'
        );

        return [
            [
                $user_indices[0],
                $card_of_normal_user->getUuid()->toString(),
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[1],
                $card_of_normal_user_with_ridi_cash_auto_charge->getUuid()->toString(),
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[2],
                $card_of_normal_user_with_ridiselect->getUuid()->toString(),
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[3],
                $card_of_leaved_user->getUuid()->toString(),
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
                $card_of_other_user->getUuid()->toString(),
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

        $card = TestUtil::registerCard(
            $user_indices[0],
            '123456'
        );
        $cases[] = [
            $user_indices[0],
            $card->getUuid()->toString(),
            []
        ];

        $card_with_ridi_cash_auto_charge_subscription = TestUtil::registerCard(
            $user_indices[1],
            '123456'
        );
        $subscription = new SubscriptionEntity(
            $card_with_ridi_cash_auto_charge_subscription->getId(),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDI_CASH_AUTO_CHARGE
        );
        SubscriptionRepository::getRepository()->save($subscription);
        $cases[] = [
            $user_indices[1],
            $card_with_ridi_cash_auto_charge_subscription->getUuid()->toString(),
            [$subscription->getId()]
        ];

        $card_with_ridiselect_subscription = TestUtil::registerCard(
            $user_indices[2],
            '123456'
        );
        $subscription = new SubscriptionEntity(
            $card_with_ridiselect_subscription->getId(),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDISELECT
        );
        SubscriptionRepository::getRepository()->save($subscription);
        $cases[] = [
            $user_indices[2],
            $card_with_ridiselect_subscription->getUuid()->toString(),
            [$subscription->getId()]
        ];

        $card_with_multiple_subscriptions = TestUtil::registerCard(
            $user_indices[3],
            '123456'
        );
        $ridi_cash_auto_charge_subscription = new SubscriptionEntity(
            $card_with_multiple_subscriptions->getId(),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDI_CASH_AUTO_CHARGE
        );
        SubscriptionRepository::getRepository()->save($ridi_cash_auto_charge_subscription);
        $ridiselect_subscription = new SubscriptionEntity(
            $card_with_multiple_subscriptions->getId(),
            PartnerRepository::getRepository()->findOneByApiKey(Uuid::fromString($partner->api_key))->getId(),
            SubscriptionConstant::PRODUCT_RIDISELECT
        );
        SubscriptionRepository::getRepository()->save($ridiselect_subscription);
        $cases[] = [
            $user_indices[3],
            $card_with_multiple_subscriptions->getUuid()->toString(),
            [
                $ridi_cash_auto_charge_subscription->getId(),
                $ridiselect_subscription->getId()
            ]
        ];

        return $cases;
    }
}
