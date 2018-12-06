<?php

use Doctrine\DBAL\Types\Type;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use RidiPay\Kernel;
use RidiPay\Library\SentryHelper;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/../vendor/autoload.php';

Type::addType('uuid_binary', UuidBinaryType::class);

$env = getenv('APP_ENV');
if ($env === false) {
    throw new \RuntimeException('APP_ENV environment variables is not defined.');
}

if (Kernel::isLocal()) {
    $dotenv_file_path = __DIR__ . '/../.env';
    if (file_exists($dotenv_file_path)) {
        if (!class_exists(Dotenv::class)) {
            throw new \RuntimeException(
                'Add "symfony/dotenv" as a Composer dependency to load variables from a .env file.'
            );
        }
        (new Dotenv())->load($dotenv_file_path);
    }

    umask(0000);
    Debug::enable();
}

if (Kernel::isProd()) {
    Request::setTrustedProxies(['10.0.0.0/16'], Request::HEADER_X_FORWARDED_ALL);
} elseif (Kernel::isStaging()) {
    Request::setTrustedProxies(['10.10.0.0/16'], Request::HEADER_X_FORWARDED_ALL);
} elseif (Kernel::isTest()) {
    Request::setTrustedProxies(['10.20.0.0/16'], Request::HEADER_X_FORWARDED_ALL);
}

$sentry_dsn = getenv('SENTRY_DSN');
if ($sentry_dsn) {
    $root_path = realpath(__DIR__ . '/../');
    $options = [
        'prefixes' => [$root_path]
    ];
    SentryHelper::registerClient($sentry_dsn, $options);
}

$kernel = new Kernel($env, Kernel::isDev());
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
