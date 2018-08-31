<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use AspectMock\Test as test;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use Ridibooks\Library\AdaptableCache;
use Ridibooks\Payment\Kcp\Company;
use RidiPay\Library\ConnectionProvider;
use RidiPay\Library\EntityManagerProvider;
use RidiPay\Transaction\Constant\PgConstant;
use RidiPay\Transaction\Entity\PartnerEntity;
use RidiPay\Transaction\Entity\PgEntity;
use RidiPay\Transaction\Entity\SubscriptionEntity;
use RidiPay\Transaction\Entity\TransactionEntity;
use RidiPay\Transaction\Entity\TransactionHistoryEntity;
use RidiPay\User\Entity\CardEntity;
use RidiPay\User\Entity\CardIssuerEntity;
use RidiPay\User\Entity\PaymentMethodEntity;
use RidiPay\User\Entity\UserActionHistoryEntity;
use RidiPay\User\Entity\UserEntity;

class TestUtil
{
    /** @var Connection */
    private static $conn = null;

    /** @var EntityManager */
    private static $em = null;

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public static function prepareDatabaseFixture()
    {
        $conn = self::getConnection();
        $conn->getSchemaManager()->dropAndCreateDatabase($conn->getDatabase());
        $conn->close();

        $em = self::getEntityManager();
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
        $pg = new PgEntity(PgConstant::KCP);
        $em->persist($pg);
        $em->flush($pg);

        // CardIssuer Fixture 생성
        foreach (Company::COMPANY_NAME_MAPPING_KO as $code => $name) {
            // TODO: 색상, 로고 Image URL 채우기
            $card_issuer = new CardIssuerEntity($pg, $code, $name, '000000', '');
            $em->persist($card_issuer);
        }
        $em->flush();
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function setUpDatabaseDoubles()
    {
        test::double(
            ConnectionProvider::class,
            ['getConnection' => self::getConnection()]
        );

        test::double(
            EntityManagerProvider::class,
            ['getEntityManager' => self::getEntityManager()]
        );
    }

    public static function tearDownDatabaseDoubles()
    {
        test::clean();
    }

    /**
     * @return EntityManager
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public static function getEntityManager(): EntityManager
    {
        if (self::$em === null || !self::$em->isOpen()) {
            $config = Setup::createAnnotationMetadataConfiguration(
                [__DIR__ . "/../../src"]
            );
            self::$em = EntityManager::create(self::getConnection(), $config);
            $platform = self::$em->getConnection()->getDatabasePlatform();
            $platform->registerDoctrineTypeMapping('enum', 'string');
            $platform->registerDoctrineTypeMapping('bit', 'integer');
        }

        return self::$em;
    }

    /**
     * @return Connection
     * @throws \Doctrine\DBAL\DBALException
     */
    public static function getConnection(): Connection
    {
        if (self::$conn === null || !self::$conn->isConnected()) {
            self::$conn = DriverManager::getConnection([
                'url' => \getenv('PHPUNIT_DATABASE_URL')
            ]);
            self::$conn->setFetchMode(\PDO::FETCH_OBJ);
            self::$conn->getConfiguration()->setResultCacheImpl(new AdaptableCache());
        }

        return self::$conn;
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
