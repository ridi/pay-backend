<?php

namespace App\Entity;

/**
 * @Table(name="pay_method", uniqueConstraints={@UniqueConstraint(name="uniq_uuid", columns={"uuid"})}, indexes={@Index(name="idx_u_idx", columns={"u_idx"})})
 * @Entity
 */
class PayMethodEntity
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
     * TODO: https://github.com/ramsey/uuid-doctrine 도입
     * @var binary
     *
     * @Column(name="uuid", type="binary", nullable=false, options={"comment"="id 값 유추 방지를 위한 uuid"})
     */
    private $uuid;

    /**
     * @var string
     *
     * @Column(name="type", type="string", length=0, nullable=false, columnDefinition="ENUM('CARD')", options={"default"="CARD","comment"="결제 수단"})
     */
    private $type = 'CARD';

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP","comment"="결제 수단 등록 시각"})
     */
    private $created_at = 'CURRENT_TIMESTAMP';

    /**
     * @var \DateTime|null
     *
     * @Column(name="deleted_at", type="datetime", nullable=true, options={"comment"="결제 수단 삭제 시각"})
     */
    private $deleted_at;
}
