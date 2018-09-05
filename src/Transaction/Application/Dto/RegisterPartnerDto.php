<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Dto;

use RidiPay\Transaction\Domain\Entity\PartnerEntity;

class RegisterPartnerDto
{
    /** @var string */
    public $api_key;

    /** @var string */
    public $secret_key;

    /**
     * @param PartnerEntity $partner
     * @throws \Exception
     */
    public function __construct(PartnerEntity $partner)
    {
        $this->api_key = $partner->getApiKey()->toString();
        $this->secret_key = $partner->getSecretKey()->toString();
    }
}