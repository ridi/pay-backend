<?php
declare(strict_types=1);

namespace RidiPay\Partner\Application\Dto;

use RidiPay\Partner\Domain\Entity\PartnerEntity;

class PartnerDto
{
    /** @var string */
    public $name;

    /** @var bool */
    public $is_first_party;

    /**
     * @param PartnerEntity $partner
     */
    public function __construct(PartnerEntity $partner)
    {
        $this->name = $partner->getName();
        $this->is_first_party = $partner->isFirstParty();
    }
}
