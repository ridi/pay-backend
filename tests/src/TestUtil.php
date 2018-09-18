<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use AspectMock\Test;
use Doctrine\ORM\Tools\SchemaTool;
use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use RidiPay\Library\Pg\Kcp\Company;
use RidiPay\Library\ConnectionProvider;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Jwt\JwtAuthorizationMiddleware;
use RidiPay\Library\OAuth2\User\DefaultUserProvider;
use RidiPay\Library\OAuth2\User\User;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Partner\Domain\Entity\PartnerEntity;
use RidiPay\Pg\Domain\Entity\PgEntity;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\User\Application\Service\CardAppService;
use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\Entity\CardIssuerEntity;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Entity\UserActionHistoryEntity;
use RidiPay\User\Domain\Entity\UserEntity;
use RidiPay\User\Domain\Exception\CardAlreadyExistsException;
use RidiPay\User\Domain\Exception\LeavedUserException;

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
        $schemaTool = new SchemaTool($em);

        $classes = array_map(
            function (string $class_name) use ($em) {
                return $em->getClassMetadata($class_name);
            },
            [
                UserEntity::class,
                UserActionHistoryEntity::class,
                PaymentMethodEntity::class,
                CardEntity::class,
                CardIssuerEntity::class,
                TransactionEntity::class,
                TransactionHistoryEntity::class,
                SubscriptionEntity::class,
                PgEntity::class,
                PartnerEntity::class
            ]
        );
        $schemaTool->createSchema($classes);

        // Pg Fixture 생성
        $pg = PgEntity::createKcp();
        $em->persist($pg);
        $em->flush($pg);

        // CardIssuer Fixture 생성
        foreach (Company::COMPANY_NAME_MAPPING_KO as $code => $name) {
            // TODO: 색상, 로고 Image URL 채우기
            $card_issuer = new CardIssuerEntity($pg->getId(), $code, $name, '000000', '');
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
        Test::double(
            DefaultUserProvider::class,
            [
                'getUser' => new User(json_encode([
                    'result' => [
                        'id' => $u_id,
                        'idx' => $u_idx,
                        'is_verified_adult' => true,
                    ],
                    'message' => '정상적으로 완료되었습니다.'
                ]))
            ]
        );
    }

    public static function tearDownOAuth2Doubles(): void
    {
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
     * @return string
     * @throws CardAlreadyExistsException
     * @throws CardRegistrationException
     * @throws LeavedUserException
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Throwable
     */
    public static function createCard(int $u_idx): string
    {
        $payment_method_id = CardAppService::registerCard(
            $u_idx,
            self::CARD['CARD_NUMBER'],
            self::CARD['CARD_EXPIRATION_DATE'],
            self::CARD['CARD_PASSWORD'],
            self::TAX_ID
        );

        return $payment_method_id;
    }
}
