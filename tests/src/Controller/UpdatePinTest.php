<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdatePinTest extends ControllerTestCase
{
    /**
     * @dataProvider userAndPinProvider
     *
     * @param int $u_idx
     * @param string $pin
     * @param int $http_status_code
     * @param null|string $error_code
     * @throws AuthorizationException
     */
    public function testUpdatePin(int $u_idx, string $pin, int $http_status_code, ?string $error_code)
    {
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $client = self::createClientWithOAuth2AccessToken();

        $body = json_encode(['pin' => $pin]);
        $client->request(Request::METHOD_PUT, '/me/pin', [], [], [], $body);
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());

        $response_content = json_decode($client->getResponse()->getContent());
        if (isset($response_content->code)) {
            $this->assertSame($error_code, $response_content->code);
        }

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * @return array
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function userAndPinProvider(): array
    {
        $user_indices = [];
        for ($i = 0; $i < 4; $i++) {
            $user_indices[] = TestUtil::getRandomUidx();
        }

        UserAppService::createUser($user_indices[0]);

        UserAppService::createUser($user_indices[1]);
        UserAppService::deleteUser($user_indices[1]);

        UserAppService::createUser($user_indices[3]);

        return [
            [
                $user_indices[0],
                self::getValidPin(),
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[1],
                self::getValidPin(),
                Response::HTTP_FORBIDDEN,
                UserErrorCodeConstant::LEAVED_USER
            ],
            [
                $user_indices[2],
                self::getValidPin(),
                Response::HTTP_NOT_FOUND,
                UserErrorCodeConstant::NOT_FOUND_USER
            ],
            [
                $user_indices[3],
                self::getInvalidPinWithShortLength(),
                Response::HTTP_BAD_REQUEST,
                CommonErrorCodeConstant::INVALID_PARAMETER
            ],
            [
                $user_indices[3],
                self::getInvalidPinIncludingUnsupportedCharacters(),
                Response::HTTP_BAD_REQUEST,
                CommonErrorCodeConstant::INVALID_PARAMETER
            ]
        ];
    }

    /**
     * @return string
     */
    private static function getValidPin(): string
    {
        return substr(str_shuffle('0123456789'), 0, 6);
    }

    /**
     * @return string
     */
    private static function getInvalidPinWithShortLength(): string
    {
        return substr(str_shuffle('0123456789'), 0, 4);
    }

    /**
     * @return string
     */
    private static function getInvalidPinIncludingUnsupportedCharacters(): string
    {
        $supported_characters = substr(str_shuffle('0123456789'), 0, 4);
        $unsupported_characters = substr(str_shuffle('abcdeefhji'), 0, 2);

        return str_shuffle($supported_characters . $unsupported_characters);
    }
}
