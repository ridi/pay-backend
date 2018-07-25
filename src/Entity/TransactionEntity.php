<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="transaction", indexes={@ORM\Index(name="idx_pay_method_id", columns={"pay_method_id"}), @ORM\Index(name="idx_partner_id", columns={"partner_id"}), @ORM\Index(name="idx_pg_id", columns={"pg_id"}), @ORM\Index(name="idx_u_idx", columns={"u_idx"})})
 * @ORM\Entity
 */
class TransactionEntity
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="bigint", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="partner_order_id", type="string", length=64, nullable=false, options={"comment"="Partner 주문 번호"})
     */
    private $partner_order_id = '';

    /**
     * @var string|null
     *
     * @ORM\Column(name="pg_transaction_id", type="string", length=64, nullable=true, options={"comment"="PG사 t_id"})
     */
    private $pg_transaction_id = '';

    /**
     * @var int
     *
     * @ORM\Column(name="amount", type="integer", nullable=false, options={"comment"="주문 금액"})
     */
    private $amount;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP","comment"="Transaction 생성 시각"})
     */
    private $created_at = 'CURRENT_TIMESTAMP';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="approved_at", type="datetime", nullable=true, options={"comment"="Transaction 승인 시각"})
     */
    private $approved_at;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="canceled_at", type="datetime", nullable=true, options={"comment"="Transaction 취소 시각"})
     */
    private $canceled_at;
}
