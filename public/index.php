<?php

use Doctrine\DBAL\Types\Type;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use Ridibooks\Library\SentryHelper;
use RidiPay\Kernel;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require __DIR__.'/../vendor/autoload.php';

Type::addType('uuid_binary', UuidBinaryType::class);

$env = getenv('APP_ENV');
if ($env === false) {
    throw new \RuntimeException('APP_ENV environment variables is not defined.');
}

$is_dev = ($env === 'dev');
if ($is_dev) {
    $dotenv_file_path = __DIR__ . '/../.env';
    if (file_exists($dotenv_file_path)) {
        (new Dotenv())->load($dotenv_file_path);
    }

    umask(0000);
    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts(explode(',', $trustedHosts));
}

$sentry_dsn = getenv('SENTRY_DSN');
if ($sentry_dsn) {
    $root_path = realpath(__DIR__ . '/../');
    $options = [
        'prefixes' => [$root_path],
    ];

    SentryHelper::enableSentry($sentry_dsn, $options);
    $client = SentryHelper::getRavenClient();
    $client->setRelease(getenv('GIT_REVISION'));
    $client->setEnvironment($env);
    $client->setProcessors([new Raven_Processor_SanitizeDataProcessor($client)]);
}

$kernel = new Kernel($env, $is_dev);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
