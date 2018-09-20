<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use AspectMock\Test;
use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\PasswordValidationApi;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Service\PasswordEntryAbuseBlockPolicy;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePasswordTest extends ControllerTestCase
{
    private const VALID_PASSWORD = 'abcde@12345';
    private const INVALID_PASSWORD = '12345@abcde';

    /**
     * @dataProvider userAndPasswordProvider
     *
     * @param int $u_idx
     * @param string $password
     * @param int $http_status_code
     * @param null|string $error_code
     * @throws AuthorizationException
     */
    public function testEnterPasswordCorrectly(
        int $u_idx,
        string $password,
        int $http_status_code,
        ?string $error_code
    ) {
        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);
        Test::double(PasswordValidationApi::class, ['isPasswordMatched' => true]);

        $body = json_encode(['password' => $password]);
        $client = self::createClientWithOAuth2AccessToken();
        $client->request(Request::METHOD_POST, '/me/password/validate', [], [], [], $body);
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());

        $response_content = json_decode($client->getResponse()->getContent());
        if (isset($response_content->code)) {
            $this->assertSame($error_code, $response_content->code);
        }

        Test::clean(PasswordValidationApi::class);
        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * @throws AuthorizationException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function testEnterPasswordInCorrectly()
    {
        $u_idx = TestUtil::getRandomUidx();
        UserAppService::createUser($u_idx);

        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);
        Test::double(PasswordValidationApi::class, ['isPasswordMatched' => false]);

        $body = json_encode(['password' => self::INVALID_PASSWORD]);
        $client = self::createClientWithOAuth2AccessToken();
        $client->request(Request::METHOD_POST, '/me/password/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        $this->assertSame(UserErrorCodeConstant::PASSWORD_UNMATCHED, json_decode($client->getResponse()->getContent())->code);

        Test::clean(PasswordValidationApi::class);
        TestUtil::tearDownOAuth2Doubles();
    }

    /**
     * @throws AuthorizationException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function testPasswordEntryBlocked()
    {
        $u_idx = TestUtil::getRandomUidx();
        UserAppService::createUser($u_idx);

        TestUtil::setUpOAuth2Doubles($u_idx, TestUtil::U_ID);
        Test::double(PasswordValidationApi::class, ['isPasswordMatched' => false]);

        $client = self::createClientWithOAuth2AccessToken();

        // PASSWORD 입력 불일치
        $policy = new PasswordEntryAbuseBlockPolicy();
        for ($try_count = 0; $try_count < $policy->getBlockThreshold() - 1; $try_count++) {
            $body = json_encode(['password' => self::INVALID_PASSWORD]);
            $client->request(Request::METHOD_POST, '/me/password/validate', [], [], [], $body);
            $this->assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
            $this->assertSame(
                UserErrorCodeConstant::PASSWORD_UNMATCHED,
                json_decode($client->getResponse()->getContent())->code
            );
        }

        // PASSWORD 연속 입력 불일치 => 일정 시간 입력 제한
        $body = json_encode(['password' => self::INVALID_PASSWORD]);
        $client->request(Request::METHOD_POST, '/me/password/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        $this->assertSame(
            UserErrorCodeConstant::PASSWORD_ENTRY_BLOCKED,
            json_decode($client->getResponse()->getContent())->code
        );

        // 일정 시간 입력 제한 이후 시도
        $body = json_encode(['password' => self::INVALID_PASSWORD]);
        $client->request(Request::METHOD_POST, '/me/password/validate', [], [], [], $body);
        $this->assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        $this->assertSame(
            UserErrorCodeConstant::PASSWORD_ENTRY_BLOCKED,
            json_decode($client->getResponse()->getContent())->code
        );
        
        Test::clean(PasswordValidationApi::class);
    }

    /**
     * @return array
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function userAndPasswordProvider(): array
    {
        $user_indices = [];
        for ($i = 0; $i < 3; $i++) {
            $user_indices[] = TestUtil::getRandomUidx();
        }

        UserAppService::createUser($user_indices[0]);

        UserAppService::createUser($user_indices[1]);
        UserAppService::deleteUser($user_indices[1]);

        return [
            [
                $user_indices[0],
                self::VALID_PASSWORD,
                Response::HTTP_OK,
                null
            ],
            [
                $user_indices[1],
                self::VALID_PASSWORD,
                Response::HTTP_FORBIDDEN,
                UserErrorCodeConstant::LEAVED_USER
            ],
            [
                $user_indices[2],
                self::VALID_PASSWORD,
                Response::HTTP_NOT_FOUND,
                UserErrorCodeConstant::NOT_FOUND_USER
            ]
        ];
    }
}
