<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Entity;

/**
 * @Table(
 *   name="transaction_history",
 *   indexes={
 *     @Index(name="idx_transaction_id", columns={"transaction_id"})
 *   }
 * )
 * @Entity(repositoryClass="RidiPay\Transaction\Domain\Repository\TransactionHistoryRepository")
 */
class TransactionHistoryEntity
{
    private const ACTION_APPROVE = 'APPROVE';
    private const ACTION_CANCEL = 'CANCEL';

    /**
     * @var int
     *
     * @Column(name="id", type="bigint", nullable=false, options={"unsigned"=true})
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var TransactionEntity
     *
     * @ManyToOne(targetEntity="RidiPay\Transaction\Domain\Entity\TransactionEntity")
     * @JoinColumn(name="transaction_id", referencedColumnName="id", nullable=false)
     */
    private $transaction;

    /**
     * @var string
     *
     * @Column(
     *   name="action",
     *   type="string",
     *   length=0,
     *   nullable=false,
     *   columnDefinition="ENUM('APPROVE','CANCEL')",
     *   options={
     *     "default"="APPROVE",
     *     "comment"="PAY: 결제, CANCEL: 취소"
     *   }
     * )
     */
    private $action;

    /**
     * @var bool
     *
     * @Column(name="is_success", type="boolean", nullable=false, options={"comment"="결제 성공 여부"})
     */
    private $is_success;

    /**
     * @var string|null
     *
     * @Column(
     *   name="pg_response_code",
     *   type="string",
     *   length=16,
     *   nullable=false,
     *   options={
     *     "comment"="PG사 결제 응답 코드"
     *   }
     * )
     */
    private $pg_response_code;

    /**
     * @var string|null
     *
     * @Column(
     *   name="pg_response_message",
     *   type="string",
     *   length=64,
     *   nullable=false,
     *   options={
     *     "comment"="PG사 결제 응답 메시지"
     *   }
     * )
     */
    private $pg_response_message;

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created_at;

    /**
     * @param TransactionEntity $transaction
     * @param bool $is_success
     * @param string $pg_response_code
     * @param string $pg_response_message
     * @return TransactionHistoryEntity
     */
    public static function createApproveHistory(
        TransactionEntity $transaction,
        bool $is_success,
        string $pg_response_code,
        string $pg_response_message
    ): TransactionHistoryEntity {
        return new TransactionHistoryEntity(
            $transaction,
            self::ACTION_APPROVE,
            $is_success,
            $pg_response_code,
            $pg_response_message
        );
    }

    /**
     * @param TransactionEntity $transaction
     * @param bool $is_success
     * @param string $pg_response_code
     * @param string $pg_response_message
     * @return TransactionHistoryEntity
     */
    public static function createCancelHistory(
        TransactionEntity $transaction,
        bool $is_success,
        string $pg_response_code,
        string $pg_response_message
    ): TransactionHistoryEntity {
        return new TransactionHistoryEntity(
            $transaction,
            self::ACTION_CANCEL,
            $is_success,
            $pg_response_code,
            $pg_response_message
        );
    }

    /**
     * @param TransactionEntity $transaction
     * @param string $action
     * @param bool $is_success
     * @param string $pg_response_code
     * @param string $pg_response_message
     */
    private function __construct(
        TransactionEntity $transaction,
        string $action,
        bool $is_success,
        string $pg_response_code,
        string $pg_response_message
    ) {
        $this->transaction = $transaction;
        $this->action = $action;
        $this->is_success = $is_success;
        $this->pg_response_code = $pg_response_code;
        $this->pg_response_message = $pg_response_message;
        $this->created_at = new \DateTime();
    }
}
