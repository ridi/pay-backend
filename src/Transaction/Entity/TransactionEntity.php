<?php

namespace RidiPay\Transaction\Entity;

/**
 * @Table(name="transaction", indexes={@Index(name="idx_payment_method_id", columns={"payment_method_id"}), @Index(name="idx_partner_id", columns={"partner_id"}), @Index(name="idx_pg_id", columns={"pg_id"}), @Index(name="idx_u_idx", columns={"u_idx"})})
 * @Entity
 */
class TransactionEntity
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
     * @Column(name="partner_order_id", type="string", length=64, nullable=false, options={"comment"="Partner 주문 번호"})
     */
    private $partner_order_id = '';

    /**
     * @var string|null
     *
     * @Column(name="pg_transaction_id", type="string", length=64, nullable=true, options={"comment"="PG사 t_id"})
     */
    private $pg_transaction_id = '';

    /**
     * @var int
     *
     * @Column(name="amount", type="integer", nullable=false, options={"comment"="주문 금액"})
     */
    private $amount;

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP","comment"="Transaction 생성 시각"})
     */
    private $created_at = 'CURRENT_TIMESTAMP';

    /**
     * @var \DateTime|null
     *
     * @Column(name="approved_at", type="datetime", nullable=true, options={"comment"="Transaction 승인 시각"})
     */
    private $approved_at;

    /**
     * @var \DateTime|null
     *
     * @Column(name="canceled_at", type="datetime", nullable=true, options={"comment"="Transaction 취소 시각"})
     */
    private $canceled_at;

    /** @var int
     *
     * @Column(name="payment_method_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="payment_method.id"})
     */
    private $payment_method_id;

    /** @var int
     *
     * @Column(name="partner_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="parnter.id"})
     */
    private $partner_id;

    /** @var int
     *
     * @Column(name="pg_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="pg.id"})
     */
    private $pg_id;

    /** @var int
     *
     * @Column(name="u_idx", type="integer", nullable=false, options={"comment"="user.u_idx"})
     */
    private $u_idx;
}
