<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *   title="RIDI Pay API",
 *   version="0.1.0"
 * )
 *
 * @OA\Server(
 *   url="https://pay-api.local.ridi.io",
 *   description="Local Server"
 * )
 * @OA\Server(
 *   url="https://pay-api.ridi.io",
 *   description="Test Server"
 * )
 * @OA\Server(
 *   url="https://pay-api.ridibooks.com",
 *   description="Production Server"
 * )
 *
 * @OA\Tag(
 *   name="public-api",
 *   description="모든 가맹점 서비스에서 호출 가능한 API"
 * )
 * @OA\Tag(
 *   name="private-api",
 *   description="RIDI Pay 내부 호출 API"
 * )
 * @OA\Tag(
 *   name="private-api-for-first-party",
 *   description="RIDI 서비스에서 호출 가능한 API"
 * )
 * @OA\Tag(
 *   name="cms-api",
 *   description="CMS에서 호출 가능한 API"
 * )
 */
$openapi = OpenApi\scan([
    __DIR__,
    __DIR__ . '/../../src'
]);
header('Content-Type: application/json');
echo $openapi->toJson();
