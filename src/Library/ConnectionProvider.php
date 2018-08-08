<?php
declare(strict_types=1);

namespace RidiPay\Library;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Ridibooks\Library\AdaptableCache;

class ConnectionProvider
{
    /** @var \Doctrine\DBAL\Connection[] */
    private static $connection_pool = [];

    /**
     * @param string $connection_group
     * @return Connection
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public static function getConnection(string $connection_group = ConnectionGroupConstant::WRITE): Connection
    {
        if (!isset(self::$connection_pool[$connection_group])
            || !self::$connection_pool[$connection_group]->isConnected()
        ) {
            self::$connection_pool[$connection_group] = self::createConnection($connection_group);
        }

        return self::$connection_pool[$connection_group];
    }

    /**
     * @param string $connection_group
     * @return Connection
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    private static function createConnection(string $connection_group): Connection
    {
        $config = new Configuration();
        $connection = DriverManager::getConnection(self::getConnectionParams($connection_group), $config);
        $connection->getConfiguration()->setResultCacheImpl(new AdaptableCache());
        $connection->setFetchMode(\PDO::FETCH_OBJ);

        return $connection;
    }

    /**
     * @param string $connection_group
     * @return array
     * @throws \Exception
     */
    private static function getConnectionParams(string $connection_group): array
    {
        $params_str = getenv('DB_PARAMS_' . $connection_group);
        if (empty($params_str)) {
            $params_str = getenv('DB_PARAMS');
            if (empty($params_str)) {
                throw new \Exception('DB connection parameters are missing!');
            }
        }

        $params = json_decode($params_str, true);
        return [
            'host' => $params['HOST'],
            'user' => $params['USER'],
            'password' => $params['PASSWORD'],
            'dbname' => $params['DB_NAME'],
            'driver' => 'pdo_mysql',
            'wrapperClass' => '\Ridibooks\Library\DB\CustomPdoConnection',
            'charset' => 'utf8',
            'driverOptions' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
        ];
    }

    public static function closeAllConnections(): void
    {
        foreach (self::$connection_pool as $connection) {
            $connection->close();
        }
        self::$connection_pool = [];
    }
}
