<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\OnetouchPaySettingChangeDeclinedException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserInformationInquiryTest extends ControllerTestCase
{
    /**
     * @dataProvider userProvider
     *
     * @param int $u_idx
     * @param null|string $payment_method_id
     * @param int $http_status_code
     * @param null|string $error_code
     * @throws AuthorizationException
     */
    public function testUserInformationInquiry(
        int $u_idx,
        ?string $payment_method_id,
        int $http_status_code,
        ?string $error_code
    ) {
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $client = self::createClientWithOAuth2AccessToken();
        $client->request(Request::METHOD_GET, '/me');

        $response_status_code = $client->getResponse()->getStatusCode();
        $response_content = $client->getResponse()->getContent();

        $this->assertSame($http_status_code, $response_status_code);
        if ($response_status_code === Response::HTTP_OK) {
            $expected_response = json_encode([
                'user_id' => TestUtil::U_ID,
                'payment_methods' => [
                    'cards' => [
                        [
                            'iin' => substr(TestUtil::CARD['CARD_NUMBER'], 0, 6),
                            'issuer_name' => '신한카드',
                            'color' => '#000000',
                            'logo_image_url' => '',
                            'subscriptions' => [],
                            'payment_method_id' => $payment_method_id
                        ]
                    ]
                ],
                'has_pin' => true,
                'is_using_onetouch_pay' => true
            ]);
            $this->assertSame($expected_response, $response_content);
        }

        $decoded_response_content = json_decode($response_content);
        if (isset($decoded_response_content->code)) {
            $this->assertSame($error_code, $decoded_response_content->code);
        }

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * @return array
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws OnetouchPaySettingChangeDeclinedException
     * @throws WrongFormattedPinException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public function userProvider(): array
    {
        $user_indices = [TestUtil::getRandomUidx(), TestUtil::getRandomUidx(), TestUtil::getRandomUidx()];

        $payment_method_id = TestUtil::registerCard(
            $user_indices[0],
            '123456',
            true,
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );

        UserAppService::createUser($user_indices[1]);
        UserAppService::deleteUser($user_indices[1]);

        return [
            [$user_indices[0], $payment_method_id, Response::HTTP_OK, null],
            [$user_indices[1], null, Response::HTTP_FORBIDDEN, UserErrorCodeConstant::LEAVED_USER],
            [$user_indices[2], null, Response::HTTP_NOT_FOUND, UserErrorCodeConstant::NOT_FOUND_USER]
        ];
    }
}
