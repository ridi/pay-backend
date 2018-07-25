<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="pg")
 * @ORM\Entity
 */
class PgEntity
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=16, nullable=false, options={"comment"="PG사"})
     */
    private $name = '';

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=0, nullable=false, columnDefinition="ENUM('ACTIVE','INACTIVE','KEPT')", options={"default"="ACTIVE","comment"="PG사 이용 상태(ACTIVE: 사용, INACTIVE: 미사용, KEPT: 기존 유저는 사용, 신규 유저는 미사용)"})
     */
    private $status = 'ACTIVE';

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $updated_at = 'CURRENT_TIMESTAMP';


}
