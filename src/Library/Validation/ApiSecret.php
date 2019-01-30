<?php
declare(strict_types=1);

namespace RidiPay\Library\Validation;

use OpenApi\Annotations as OA;

class ApiSecret
{
    /**
     * @OA\Parameter(
     *   name="Api-Key",
     *   in="header",
     *   required=true,
     *   description="가맹점에서 RIDI Pay API 연동을 위해 필요한 ID",
     *   example="550E8400-E29B-41D4-A716-446655440000",
     *   @OA\Schema(type="string")
     * )
     *
     * @var string
     */
    private $api_key;

    /**
     * @OA\Parameter(
     *   name="Secret-Key",
     *   in="header",
     *   required=true,
     *   description="가맹점에서 RIDI Pay API 연동을 위해 필요한 Secret",
     *   example="550E8400-E29B-41D4-A716-446655440000",
     *   @OA\Schema(type="string")
     * )
     *
     * @var string
     */
    private $secret_key;

    /**
     * @param string $api_key
     * @param string $secret_key
     */
    public function __construct(string $api_key, string $secret_key)
    {
        $this->api_key = $api_key;
        $this->secret_key = $secret_key;
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->api_key;
    }

    /**
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->secret_key;
    }
}