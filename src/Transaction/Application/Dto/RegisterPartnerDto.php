<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

class RegisterPartnerDto
{
    /** @var string */
    public $api_key;

    /** @var string */
    public $secret_key;

    /**
     * @param string $api_key
     * @param string $secret_key
     */
    public function __construct(string $api_key, string $secret_key)
    {
        $this->api_key = $api_key;
        $this->secret_key = $secret_key;
    }
}
