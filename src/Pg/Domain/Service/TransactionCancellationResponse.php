<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

class TransactionCancellationResponse extends PgResponse
{
    /** @var null|int */
    private $amount;

    /** @var null|\DateTime */
    private $canceled_at;

    /** @var bool */
    private $is_already_canceled;

    /**
     * @param bool $is_success
     * @param string $response_code
     * @param string $response_message
     * @param bool $is_already_canceled
     * @param null|int $amount
     * @param null|\DateTime $canceled_at
     */
    public function __construct(
        bool $is_success,
        string $response_code,
        string $response_message,
        bool $is_already_canceled,
        ?int $amount,
        ?\DateTime $canceled_at
    ) {
        parent::__construct($is_success, $response_code, $response_message);

        $this->amount = $amount;
        $this->canceled_at = $canceled_at;
        $this->is_already_canceled = $is_already_canceled;
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
    public function getCanceledAt(): \DateTime
    {
        return $this->canceled_at;
    }

    /**
     * @return bool
     */
    public function isAlreadyCanceled(): bool
    {
        return $this->is_already_canceled;
    }
}
