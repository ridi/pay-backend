<?php

namespace RidiPay\Transaction\Entity;

/**
 * @Table(name="transaction_history", indexes={@Index(name="idx_transaction_id", columns={"transaction_id"})})
 * @Entity
 */
class TransactionHistoryEntity
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
     * @Column(name="action", type="string", length=0, nullable=false, columnDefinition="ENUM('APPROVE','CANCEL')", options={"default"="APPROVE","comment"="PAY: 결제, CANCEL: 취소"})
     */
    private $action = 'APPROVE';

    /**
     * @var bool
     *
     * @Column(name="is_success", type="boolean", nullable=false, options={"comment"="결제 성공 여부"})
     */
    private $is_success;

    /**
     * @var string|null
     *
     * @Column(name="pg_response_code", type="string", length=16, nullable=true, options={"comment"="PG사 결제 응답 코드"})
     */
    private $pg_response_code = '';

    /**
     * @var string|null
     *
     * @Column(name="pg_response_message", type="string", length=64, nullable=true, options={"comment"="PG사 결제 응답 메시지"})
     */
    private $pg_response_message = '';

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created_at = 'CURRENT_TIMESTAMP';

    /** @var int
     *
     * @Column(name="transaction_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="transaction.id"})
     */
    private $transaction_id;
}
