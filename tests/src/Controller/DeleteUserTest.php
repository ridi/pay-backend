<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Tests\TestUtil;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteUserTest extends ControllerTestCase
{
    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass()
    {
        TestUtil::setUpJwtDoubles();
    }

    public static function tearDownAfterClass()
    {
        TestUtil::tearDownJwtDoubles();
    }

    /**
     * @dataProvider userProvider
     *
     * @param int $u_idx
     * @param int $http_status_code
     * @param null|string $error_code
     */
    public function testDeleteUser(int $u_idx, int $http_status_code, ?string $error_code)
    {
        $client = self::createClient();
        $client->request(Request::METHOD_DELETE, '/users/' . $u_idx);
        $this->assertSame($http_status_code, $client->getResponse()->getStatusCode());

        $response_content = json_decode($client->getResponse()->getContent());
        if (isset($response_content->code)) {
            $this->assertSame($error_code, $response_content->code);
        }
    }

    /**
     * @return array
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function userProvider(): array
    {
        $user_indices = [TestUtil::getRandomUidx(), TestUtil::getRandomUidx(), TestUtil::getRandomUidx()];

        UserAppService::createUser($user_indices[0]);

        UserAppService::createUser($user_indices[1]);
        UserAppService::deleteUser($user_indices[1]);

        return [
            [$user_indices[0], Response::HTTP_OK, null],
            [$user_indices[1], Response::HTTP_FORBIDDEN, UserErrorCodeConstant::LEAVED_USER],
            [$user_indices[2], Response::HTTP_NOT_FOUND, UserErrorCodeConstant::NOT_FOUND_USER]
        ];
    }
}
