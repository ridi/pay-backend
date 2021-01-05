<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserInformationInquiryTest extends ControllerTestCase
{
    /**
     * @dataProvider userProvider
     *
     * @param int $u_idx
     * @param CardEntity|null $card
     * @param int $http_status_code
     * @param string|null $error_code
     */
    public function testUserInformationInquiry(
        int $u_idx,
        ?CardEntity $card,
        int $http_status_code,
        ?string $error_code
    ) {
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_GET, '/me');

        $response_status_code = $client->getResponse()->getStatusCode();
        $response_content = $client->getResponse()->getContent();

        $this->assertSame($http_status_code, $response_status_code);
        if (is_null($error_code)) {
            $expected_response = json_encode([
                'user_id' => TestUtil::U_ID,
                'payment_methods' => [
                    'cards' => [
                        [
                            'payment_method_id' => $card->getUuid()->toString(),
                            'iin' => substr(TestUtil::CARD['CARD_NUMBER'], 0, 6),
                            'issuer_name' => 'KB국민카드',
                            'color' => '#000000',
                            'logo_image_url' => '',
                            'subscriptions' => [],
                        ]
                    ]
                ],
                'has_pin' => true
            ]);
            $this->assertEquals($expected_response, $response_content);
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
     * @throws WrongFormattedPinException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public function userProvider(): array
    {
        $user_indices = [TestUtil::getRandomUidx(), TestUtil::getRandomUidx(), TestUtil::getRandomUidx()];

        $card = TestUtil::registerCard($user_indices[0], '123456');

        UserAppService::createUser($user_indices[1]);
        UserAppService::deleteUser($user_indices[1]);

        return [
            [$user_indices[0], $card, Response::HTTP_OK, null],
            [$user_indices[1], null, Response::HTTP_FORBIDDEN, UserErrorCodeConstant::LEAVED_USER],
            [$user_indices[2], null, Response::HTTP_OK, UserErrorCodeConstant::NOT_FOUND_USER]
        ];
    }
}
