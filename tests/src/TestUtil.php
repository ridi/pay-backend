<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use AspectMock\Test;
use Doctrine\ORM\Tools\SchemaTool;
use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use Ridibooks\OAuth2\Authorization\Token\JwtToken;
use Ridibooks\OAuth2\Authorization\Validator\JwtTokenValidator;
use Ridibooks\OAuth2\Symfony\Provider\DefaultUserProvider;
use Ridibooks\OAuth2\Symfony\Provider\User;
use RidiPay\Library\Pg\Kcp\Company;
use RidiPay\Library\ConnectionProvider;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Jwt\JwtAuthorizationMiddleware;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\Entity\PgEntity;
use RidiPay\User\Application\Service\CardAppService;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Entity\CardIssuerEntity;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnauthorizedCardRegistrationException;
use RidiPay\User\Domain\Exception\UnsupportedPaymentMethodException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;

class TestUtil
{
    public const U_ID = 'ridipay';

    // 신한카드
    public const CARD = [
        'CARD_NUMBER' => '4499140000000000',
        'CARD_EXPIRATION_DATE' => '2511',
        'CARD_PASSWORD' => '12'
    ];
    public const TAX_ID = '940101';

    /**
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public static function prepareDatabaseFixture()
    {
        $conn = ConnectionProvider::getConnection();
        $conn->getSchemaManager()->dropAndCreateDatabase($conn->getDatabase());
        $conn->close();

        $em = EntityManagerProvider::getEntityManager();
        $schema_tool = new SchemaTool($em);
        $schema_tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        // Pg Fixture 생성
        $pg = PgEntity::createKcp();
        $em->persist($pg);
        $em->flush($pg);

        // CardIssuer Fixture 생성
        foreach (Company::COMPANY_NAME_MAPPING_KO as $code => $name) {
            // TODO: 색상, 로고 Image URL 채우기
            $card_issuer = new CardIssuerEntity($pg->getId(), $code, $name, '#000000', '');
            $em->persist($card_issuer);
        }
        $em->flush();
    }

    /**
     * @param int $u_idx
     * @param string $u_id
     * @throws AuthorizationException
     */
    public static function setUpOAuth2Doubles(int $u_idx, string $u_id): void
    {
        $token = new \stdClass();
        $token->sub = '';
        $token->exp = 60 * 5;
        $token->u_idx = $u_idx;
        $token->client_id = '';
        $token->scope = '';

        Test::double(
            JwtTokenValidator::class,
            [
                'validateToken' => JwtToken::createFrom($token)
            ]
        );
        Test::double(
            DefaultUserProvider::class,
            [
                'getUser' => new User(json_encode([
                    'result' => [
                        'id' => $u_id,
                        'idx' => $u_idx,
                        'email' => 'oauth2-test@ridi.com',
                        'is_verified_adult' => true,
                    ],
                    'message' => '정상적으로 완료되었습니다.'
                ]))
            ]
        );
    }

    public static function tearDownOAuth2Doubles(): void
    {
        Test::clean(JwtTokenValidator::class);
        Test::clean(DefaultUserProvider::class);
    }

    /**
     * @throws \Exception
     */
    public static function setUpJwtDoubles(): void
    {
        Test::double(JwtAuthorizationMiddleware::class, ['authorize' => null]);
    }

    public static function tearDownJwtDoubles(): void
    {
        Test::clean(JwtAuthorizationMiddleware::class);
    }

    /**
     * @return int
     */
    public static function getRandomUidx(): int
    {
        $min = 1000000;
        $max = 5000000;

        return rand($min, $max);
    }

    /**
     * @param int $u_idx
     * @param string $pin
     * @param string $card_number
     * @param string $card_expiration_date
     * @param string $card_password
     * @param string $tax_id
     * @return string
     * @throws CardRegistrationException
     * @throws LeavedUserException
     * @throws NotFoundUserException
     * @throws UnauthorizedCardRegistrationException
     * @throws UnsupportedPaymentMethodException
     * @throws UnsupportedPgException
     * @throws WrongFormattedPinException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function registerCard(
        int $u_idx,
        string $pin,
        string $card_number,
        string $card_expiration_date,
        string $card_password,
        string $tax_id
    ) {
        $oauth2_user = new User(json_encode([
            'result' => [
                'id' => self::U_ID,
                'idx' => $u_idx,
                'email' => 'oauth2-test@ridi.com',
                'is_verified_adult' => true,
            ],
            'message' => '정상적으로 완료되었습니다.'
        ]));

        // 1단계: 카드 정보 등록
        CardAppService::registerCard(
            $oauth2_user->getUidx(),
            $card_number,
            $card_expiration_date,
            $card_password,
            $tax_id
        );
        // 2단계: 결제 비밀번호 정보 등록
        UserAppService::createPin($oauth2_user->getUidx(), $pin);
        // 3단계: 1 ~ 2단계의 등록 정보 저장
        CardAppService::finishCardRegistration($oauth2_user);

        $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);

        return $payment_methods->cards[0]->payment_method_id;
    }
}
