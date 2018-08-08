<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Entity;

use RidiPay\Transaction\Constant\PgConstant;

/**
 * @Table(name="pg")
 * @Entity(repositoryClass="RidiPay\Transaction\Repository\PgRepository")
 */
class PgEntity
{
    /**
     * @var int
     *
     * @Column(name="id", type="integer", nullable=false, options={"unsigned"=true})
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @Column(name="name", type="string", length=16, nullable=false, options={"comment"="PG사"})
     */
    private $name;

    /**
     * @var string
     *
     * @Column(name="status", type="string", length=0, nullable=false, columnDefinition="ENUM('ACTIVE','INACTIVE','KEPT')", options={"default"="ACTIVE","comment"="PG사 이용 상태(ACTIVE: 사용, INACTIVE: 미사용, KEPT: 기존 유저는 사용, 신규 유저는 미사용)"})
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @Column(name="updated_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $updated_at;

    /**
     * @param string $name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
        $this->status = PgConstant::STATUS_ACTIVE;
        $this->updated_at = new \DateTime();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}
