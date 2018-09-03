<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

class CancelTransactionResponse extends PgResponse
{
    /** @var int */
    private $amount;

    /** @var \DateTime */
    private $canceled_at;

    /**
     * @param bool $is_success
     * @param string $response_code
     * @param string $response_message
     * @param int $amount
     * @param \DateTime $canceled_at
     */
    public function __construct(bool $is_success, string $response_code, string $response_message, int $amount, \DateTime $canceled_at)
    {
        parent::__construct($is_success, $response_code, $response_message);

        $this->amount = $amount;
        $this->canceled_at = $canceled_at;
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
}
