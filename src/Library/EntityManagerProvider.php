<?php
declare(strict_types=1);

namespace App\Library;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\Setup;
use Ridibooks\Library\AdaptableCache;

class EntityManagerProvider
{
    /** @var EntityManager[] */
    private static $entity_manager_pool;

    /**
     * @param string $connection_group
     * @return EntityManager
     */
    public static function getEntityManager(string $connection_group = ConnectionGroupConstant::WRITE): EntityManager
    {
        if (!isset(self::$entity_manager_pool[$connection_group])
            || !self::$entity_manager_pool[$connection_group]->isOpen()
        ) {
            self::$entity_manager_pool[$connection_group] = self::createEntityManager($connection_group);
        }
        return self::$entity_manager_pool[$connection_group];
    }

    /**
     * @param string $connection_group
     * @return EntityManager
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    protected static function createEntityManager(string $connection_group): EntityManager
    {
        $is_dev_mode = getenv('APP_ENV') !== 'prod';
        $config = Setup::createAnnotationMetadataConfiguration(
            [__DIR__ . '/../src'],
            $is_dev_mode,
            null,
            new AdaptableCache()
        );

        if (!$is_dev_mode) {
            $entity_version = getenv('GIT_REVISION');

            /** @var \Doctrine\Common\Cache\CacheProvider $metadata_cache */
            $metadata_cache = $config->getMetadataCacheImpl();
            $metadata_cache->setNamespace($entity_version);

            $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);
            $config->setProxyDir(sys_get_temp_dir() . '/doctrine_proxy/' . $entity_version);
        }

        $connection = ConnectionProvider::getConnection($connection_group);
        $entity_manager = EntityManager::create($connection, $config);
        $platform = $entity_manager->getConnection()->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');
        $platform->registerDoctrineTypeMapping('bit', 'integer');

        return $entity_manager;
    }
}
