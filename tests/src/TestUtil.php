<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use AspectMock\Test as test;
use Doctrine\ORM\Tools\SchemaTool;
use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use RidiPay\Library\Pg\Kcp\Company;
use RidiPay\Library\ConnectionProvider;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Library\Jwt\JwtAuthorizationMiddleware;
use RidiPay\Library\OAuth2\User\DefaultUserProvider;
use RidiPay\Library\OAuth2\User\User;
use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Transaction\Domain\Entity\PartnerEntity;
use RidiPay\Pg\Domain\Entity\PgEntity;
use RidiPay\Transaction\Domain\Entity\SubscriptionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionEntity;
use RidiPay\Transaction\Domain\Entity\TransactionHistoryEntity;
use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\Entity\CardIssuerEntity;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Entity\UserActionHistoryEntity;
use RidiPay\User\Domain\Entity\UserEntity;

class TestUtil
{
    public const U_ID = 'ridipay';

    /**
     * @throws UnsupportedPgException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public static function prepareDatabaseFixture()
    {
        self::setUpDatabaseDoubles();

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

        self::tearDownDatabaseDoubles();
    }

    /**
     * @throws \Exception
     */
    public static function setUpDatabaseDoubles(): void
    {
        test::double(
            ConnectionProvider::class,
            ['getConnectionParams' => ['url' => \getenv('PHPUNIT_DATABASE_URL')]]
        );
    }

    public static function tearDownDatabaseDoubles(): void
    {
        test::clean(ConnectionProvider::class);
    }

    /**
     * @param int $u_idx
     * @param string $u_id
     * @throws AuthorizationException
     */
    public static function setUpOAuth2Doubles(int $u_idx, string $u_id): void
    {
        test::double(
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
        test::clean(DefaultUserProvider::class);
    }

    /**
     * @throws \Exception
     */
    public static function setUpJwtDoubles(): void
    {
        test::double(JwtAuthorizationMiddleware::class, ['authorize' => null]);
    }

    public static function tearDownJwtDoubles(): void
    {
        test::double(JwtAuthorizationMiddleware::class);
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
}
