<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Entity;

use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\PgConstant;

/**
 * @Table(
 *   name="pg",
 *   uniqueConstraints={
 *     @UniqueConstraint(name="uniq_name", columns={"name"})
 *   }
 * )
 * @Entity(repositoryClass="RidiPay\Pg\Domain\Repository\PgRepository")
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
     * @Column(
     *   name="status",
     *   type="string",
     *   length=0,
     *   nullable=false,
     *   columnDefinition="ENUM('ACTIVE','INACTIVE','KEPT')",
     *   options={
     *     "default"="ACTIVE",
     *     "comment"="PG사 이용 상태(ACTIVE: 사용, INACTIVE: 미사용, KEPT: 기존 유저는 사용, 신규 유저는 미사용)"
     *   }
     * )
     */
    private $status;

    /**
     * @var \DateTime
     *
     * @Column(
     *   name="updated_at",
     *   type="datetime",
     *   columnDefinition="DATETIME on update CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL"
     * )
     */
    private $updated_at;

    /**
     * @return PgEntity
     * @throws UnsupportedPgException
     */
    public static function createKcp(): PgEntity
    {
        return new self(PgConstant::KCP);
    }

    /**
     * @param string $name
     * @throws UnsupportedPgException
     */
    private function __construct(string $name)
    {
        $this->setName($name);
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

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @throws UnsupportedPgException
     */
    private function setName(string $name): void
    {
        if (!in_array($name, PgConstant::AVAILABLE_PGS, true)) {
            throw new UnsupportedPgException();
        }

        $this->name = $name;
    }
}
