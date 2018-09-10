<?php
declare(strict_types=1);

namespace RidiPay\Library;

class SentryHelper
{
    /** @var \Raven_Client */
    private static $client;

    /**
     * @param string $dsn
     * @param array $options
     */
    public static function registerClient(string $dsn, array $options = []): void
    {
        if (is_null(self::$client)) {
            \Raven_Autoloader::register();

            self::$client = new \Raven_Client($dsn, $options);
            self::$client->setRelease(getenv('GIT_REVISION'));
            self::$client->setEnvironment(getenv('APP_ENV'));

            $error_handler = new \Raven_ErrorHandler(self::$client);
            $error_handler->registerExceptionHandler();
            $error_handler->registerErrorHandler(true, E_ALL & ~E_NOTICE & ~E_STRICT);
            $error_handler->registerShutdownFunction();
        }
    }

    /**
     * @return \Raven_Client
     */
    public static function getClient(): \Raven_Client
    {
        return self::$client;
    }
}
