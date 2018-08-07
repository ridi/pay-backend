<?php

use Doctrine\DBAL\Types\Type;
use Ramsey\Uuid\Doctrine\UuidBinaryType;
use RidiPay\Tests\TestUtil;
use AspectMock\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$aspect_mock_kernel = Kernel::getInstance();
$aspect_mock_kernel->init([
    'cacheDir' => join(DIRECTORY_SEPARATOR, [sys_get_temp_dir(), 'aspect_mock']),
    'debug' => true,
    'includePaths' => [
        __DIR__ . '/../src'
    ]
]);

Type::addType('uuid_binary', UuidBinaryType::class);

TestUtil::prepareDatabaseFixture();

