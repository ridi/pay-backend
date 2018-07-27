<?php

namespace App\Entity;

/**
 * @Table(name="user_action_history", indexes={@Index(name="idx_u_idx", columns={"u_idx"})})
 * @Entity
 */
class UserActionHistoryEntity
{
    /**
     * @var int
     *
     * @Column(name="id", type="bigint", nullable=false, options={"unsigned"=true})
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @Column(name="action", type="string", length=32, nullable=false, options={"comment"="RIDI PAY 사용자 액션"})
     */
    private $action = '';

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created_at = 'CURRENT_TIMESTAMP';
}
