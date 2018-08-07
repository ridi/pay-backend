<?php
declare(strict_types=1);

namespace RidiPay\User\Entity;

/**
 * @Table(name="user_action_history", indexes={@Index(name="idx_u_idx", columns={"u_idx"})})
 * @Entity(repositoryClass="RidiPay\User\Repository\UserActionHistoryRepository")
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
     * @var int
     *
     * @Column(name="u_idx", type="integer", nullable=false)
     */
    private $u_idx;

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
    private $created_at;

    /**
     * @param string $action
     */
    public function __construct(int $u_idx, string $action)
    {
        $this->u_idx = $u_idx;
        $this->action = $action;
        $this->created_at = new \DateTime();
    }
}
