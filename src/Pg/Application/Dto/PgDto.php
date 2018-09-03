<?php
declare(strict_types=1);

namespace RidiPay\Pg\Application\Dto;

use RidiPay\Pg\Domain\Entity\PgEntity;

class PgDto
{
    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /**
     * @param PgEntity $pg
     */
    public function __construct(PgEntity $pg)
    {
        $this->id = $pg->getId();
        $this->name = $pg->getName();
    }
}
