<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="user")
 * @ORM\Entity
 */
class UserEntity
{
    /**
     * @var int
     *
     * @ORM\Column(name="u_idx", type="integer", nullable=false, options={"comment"="RIDIBOOKS 유저 고유 번호"})
     * @ORM\Id
     */
    private $u_idx;

    /**
     * @var string|null
     *
     * @ORM\Column(name="pin", type="string", length=255, nullable=true, options={"comment"="결제 비밀번호"})
     */
    private $pin;

    /**
     * @var bool|null
     *
     * @ORM\Column(name="is_using_onetouch_pay", type="boolean", nullable=true, options={"comment"="원터치 결제 사용 여부"})
     */
    private $is_using_onetouch_pay;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP","comment"="RIDI PAY 가입 시각(최초 결제 수단 등록일)"})
     */
    private $created_at = 'CURRENT_TIMESTAMP';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="leaved_at", type="datetime", nullable=true, options={"comment"="회원 탈퇴로 인한 RIDI PAY 해지 시각"})
     */
    private $leaved_at;
}