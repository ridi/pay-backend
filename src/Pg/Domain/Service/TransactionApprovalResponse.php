<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

class TransactionApprovalResponse extends PgResponse
{
    /** @var null|string */
    private $pg_transaction_id;

    /** @var null|int */
    private $amount;

    /** @var null|\DateTime */
    private $approved_at;

    /**
     * @param bool $is_success
     * @param string $response_code
     * @param string $response_message
     * @param null|string $pg_transaction_id
     * @param null|int $amount
     * @param null|\DateTime $approved_at
     */
    public function __construct(
        bool $is_success,
        string $response_code,
        string $response_message,
        ?string $pg_transaction_id,
        ?int $amount,
        ?\DateTime $approved_at
    ) {
        parent::__construct($is_success, $response_code, $response_message);

        $this->pg_transaction_id = $pg_transaction_id;
        $this->amount = $amount;
        $this->approved_at = $approved_at;
    }

    /**
     * @return string
     */
    public function getPgTransactionId(): string
    {
        return $this->pg_transaction_id;
    }

    /**
     * @return int
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @return \DateTime
     */
    public function getApprovedAt(): \DateTime
    {
        return $this->approved_at;
    }
}
