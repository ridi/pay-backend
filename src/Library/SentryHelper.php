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
        if (!isset(self::$client)) {
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
     * @param \Exception $e
     * @param array $data
     * @param null $logger
     * @param null $vars
     */
    public static function captureException(\Exception $e, array $data = [], $logger = null, $vars = null)
    {
        if (!isset(self::$client)) {
            return;
        }

        self::$client->captureException($e, $data, $logger, $vars);
    }

    /**
     * @param string $message
     * @param array $params
     * @param array $data
     * @param bool $stack
     * @param null $vars
     */
    public static function captureMessage(
        string $message,
        array $params = [],
        array $data = [],
        bool $stack = false,
        $vars = null
    ) {
        if (!isset(self::$client)) {
            return;
        }

        self::$client->captureMessage($message, $params, $data, $stack, $vars);
    }
}
