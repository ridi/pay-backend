<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Entity;

/**
 * @Table(
 *   name="user_action_history",
 *   indexes={
 *     @Index(name="idx_u_idx", columns={"u_idx"})
 *   }
 * )
 * @Entity(repositoryClass="RidiPay\User\Domain\Repository\UserActionHistoryRepository")
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
     * @var UserEntity
     *
     * @ManyToOne(targetEntity="RidiPay\User\Domain\Entity\UserEntity")
     * @JoinColumn(name="u_idx", referencedColumnName="u_idx", nullable=false)
     */
    private $user;

    /**
     * @var string
     *
     * @Column(name="action", type="string", length=32, nullable=false, options={"comment"="RIDI PAY 사용자 액션"})
     */
    private $action;

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created_at;

    /**
     * @param UserEntity $user
     * @param string $action
     */
    public function __construct(UserEntity $user, string $action)
    {
        $this->user = $user;
        $this->action = $action;
        $this->created_at = new \DateTime();
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->created_at;
    }
}
