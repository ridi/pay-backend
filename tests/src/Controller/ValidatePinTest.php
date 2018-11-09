<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;
use RidiPay\User\Domain\Service\PinEntryAbuseBlockPolicy;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePinTest extends ControllerTestCase
{
    private const VALID_PIN = '123456';
    private const INVALID_PIN = '654321';

    /**
     * @dataProvider userAndPinProvider
     *
     * @param int $u_idx
     * @param string $pin
     * @param int $http_status_code
     * @param null|string $error_code
     * @throws AuthorizationException
     */
    public function testValidatePin(int $u_idx, string $pin, int $http_status_code, ?string $error_code)
    {
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);

        $body = json_encode(['pin' => $pin]);
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);
        $client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $body);
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());

        $response_content = json_decode($client->getResponse()->getContent());
        if (isset($response_content->code)) {
            $this->assertSame($error_code, $response_content->code);
        }

        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * @throws AuthorizationException
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws WrongFormattedPinException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public function testPinEntryBlocked()
    {
        $u_idx = TestUtil::getRandomUidx();
        TestUtil::registerCard(
            $u_idx,
            self::VALID_PIN,
            true,
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );

        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);
        $client = self::createClientWithOAuth2AccessToken([], ['CONTENT_TYPE' => 'application/json']);

        // PIN 입력 불일치
        $policy = new PinEntryAbuseBlockPolicy();
        for ($try_count = 0; $try_count < $policy->getBlockThreshold() - 1; $try_count++) {
            $body = json_encode(['pin' => self::INVALID_PIN]);
            $client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $body);
            $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
            $this->assertSame(
                UserErrorCodeConstant::PIN_UNMATCHED,
                json_decode($client->getResponse()->getContent())->code
            );
        }

        // PIN 연속 입력 불일치 => 일정 시간 입력 제한
        $body = json_encode(['pin' => self::INVALID_PIN]);
        $client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        $this->assertSame(
            UserErrorCodeConstant::PIN_ENTRY_BLOCKED,
            json_decode($client->getResponse()->getContent())->code
        );

        // 일정 시간 입력 제한 이후 시도
        $body = json_encode(['pin' => self::INVALID_PIN]);
        $client->request(Request::METHOD_POST, '/me/pin/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        $this->assertSame(
            UserErrorCodeConstant::PIN_ENTRY_BLOCKED,
            json_decode($client->getResponse()->getContent())->code
        );

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
    public function userAndPinProvider(): array
    {
        $user_indices = [];
        for ($i = 0; $i < 4; $i++) {
            $user_indices[] = TestUtil::getRandomUidx();
        }

        TestUtil::registerCard(
            $user_indices[0],
            self::VALID_PIN,
            true,
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );

        TestUtil::registerCard(
            $user_indices[1],
            self::VALID_PIN,
            true,
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );
        UserAppService::deleteUser($user_indices[1]);

        TestUtil::registerCard(
            $user_indices[3],
            self::VALID_PIN,
            true,
            TestUtil::CARD['CARD_NUMBER'],
            TestUtil::CARD['CARD_EXPIRATION_DATE'],
            TestUtil::CARD['CARD_PASSWORD'],
            TestUtil::TAX_ID
        );

        return [
            [$user_indices[0], self::VALID_PIN, Response::HTTP_OK, null],
            [$user_indices[1], self::VALID_PIN, Response::HTTP_FORBIDDEN, UserErrorCodeConstant::LEAVED_USER],
            [$user_indices[2], self::VALID_PIN, Response::HTTP_NOT_FOUND, UserErrorCodeConstant::NOT_FOUND_USER],
            [$user_indices[3], self::INVALID_PIN, Response::HTTP_BAD_REQUEST, UserErrorCodeConstant::PIN_UNMATCHED]
        ];
    }
}
