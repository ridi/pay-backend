<?php
declare(strict_types=1);

namespace RidiPay\Library;

use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Tools\Setup;
use RidiPay\Kernel;

class EntityManagerProvider
{
    /** @var EntityManager[] */
    private static $entity_manager_pool;

    /**
     * @return EntityManager
     * @throws DBALException
     * @throws ORMException
     */
    public static function getEntityManager(): EntityManager
    {
        $connection_group = ConnectionGroupConstant::MASTER;

        if (!isset(self::$entity_manager_pool[$connection_group])
            || !self::$entity_manager_pool[$connection_group]->isOpen()
        ) {
            self::$entity_manager_pool[$connection_group] = self::createEntityManager();
        }

        return self::$entity_manager_pool[$connection_group];
    }

    /**
     * @return EntityManager
     * @throws DBALException
     * @throws ORMException
     */
    private static function createEntityManager(): EntityManager
    {
        $is_dev = Kernel::isDev();
        $config = Setup::createAnnotationMetadataConfiguration(
            [
                __DIR__ . '/../Partner',
                __DIR__ . '/../Pg',
                __DIR__ . '/../Transaction',
                __DIR__ . '/../User',
            ],
            $is_dev
        );

        if ($is_dev) {
            $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_EVAL);
        } else {
            $entity_version = getenv('GIT_REVISION', true);

            /** @var \Doctrine\Common\Cache\CacheProvider $metadata_cache */
            $metadata_cache = $config->getMetadataCacheImpl();
            $metadata_cache->setNamespace($entity_version);

            $config->setAutoGenerateProxyClasses(ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS);
            $config->setProxyDir(sys_get_temp_dir() . '/doctrine_proxy/' . $entity_version);
        }

        $connection = ConnectionProvider::getConnection();
        $entity_manager = EntityManager::create($connection, $config);
        $platform = $entity_manager->getConnection()->getDatabasePlatform();
        $platform->registerDoctrineTypeMapping('enum', 'string');
        $platform->registerDoctrineTypeMapping('bit', 'integer');

        return $entity_manager;
    }
}
