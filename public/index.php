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

$env = getenv('APP_ENV', true);
if ($env === false) {
    throw new \RuntimeException("An environment variable 'APP_ENV' is not defined.");
}

if (Kernel::isLocal()) {
    $dotenv_file_path = __DIR__ . '/../.env';
    if (!file_exists($dotenv_file_path)) {
        throw new \RuntimeException("A .env file doesn't exist.");
    }
    (new Dotenv())->load($dotenv_file_path);

    umask(0000);
    Debug::enable();
}

if (Kernel::isProd() || Kernel::isStaging() || Kernel::isTest()) {
    $vpc_cidr = getenv('VPC_CIDR', true);
    if ($vpc_cidr === false) {
        throw new \RuntimeException("An environment variable 'VPC_CIDR' is not defined.");
    }

    Request::setTrustedProxies([$vpc_cidr], Request::HEADER_X_FORWARDED_ALL);
}

$sentry_dsn = getenv('SENTRY_DSN', true);
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
