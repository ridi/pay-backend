<?php
declare(strict_types=1);

namespace RidiPay\Library;

use Doctrine\Common\Cache\ApcuCache;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;

class ConnectionProvider
{
    /** @var Connection[] */
    private static $connection_pool = [];

    /**
     * @return Connection
     * @throws DBALException
     */
    public static function getConnection(): Connection
    {
        $connection_group = ConnectionGroupConstant::MASTER;

        if (!isset(self::$connection_pool[$connection_group])
            || !self::$connection_pool[$connection_group]->isConnected()
        ) {
            self::$connection_pool[$connection_group] = self::createConnection();
        }

        return self::$connection_pool[$connection_group];
    }

    /**
     * @return Connection
     * @throws DBALException
     */
    private static function createConnection(): Connection
    {
        $config = new Configuration();
        $connection = DriverManager::getConnection(self::getConnectionParams(), $config);
        $connection->getConfiguration()->setResultCacheImpl(new ApcuCache());
        $connection->setFetchMode(\PDO::FETCH_OBJ);

        return $connection;
    }

    /**
     * @return array
     * @throws \Exception
     */
    private static function getConnectionParams(): array
    {
        $database_url = getenv('DATABASE_URL', true);
        if (empty($database_url)) {
            throw new \Exception('DB connection parameters are missing!');
        }

        return [
            'url' => $database_url,
            'charset' => 'utf8',
            'driverOptions' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
        ];
    }
}
