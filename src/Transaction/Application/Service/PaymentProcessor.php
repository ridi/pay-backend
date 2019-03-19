<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Application\Service;

use Predis\Client;
use RidiPay\Library\TimeUnitConstant;
use RidiPay\Transaction\Application\Dto\ApproveTransactionDto;
use RidiPay\Transaction\Application\Exception\AlreadyRunningTransactionException;

abstract class PaymentProcessor
{
    private const STATUS_RUNNING = 'RUNNING';
    private const STATUS_COMPLETED = 'COMPLETED';

    /** @var string 중복 결제 방지를 위한 identifier */
    private $idempotency_id;

    /** @var Client */
    private $redis;

    /**
     * @param string $idempotency_id
     */
    public function __construct(string $idempotency_id)
    {
        $this->idempotency_id = hash('sha256', $idempotency_id);
        $this->redis = new Client(['host' => getenv('REDIS_HOST')]);
    }

    /**
     * @return ApproveTransactionDto
     * @throws AlreadyRunningTransactionException
     */
    public function process(): ApproveTransactionDto
    {
        if ($this->isRunnable()) {
            $this->setTtl();

            $result = $this->run();
            $this->setResult($result);

            return $result;
        } elseif ($this->isCompleted()) {
            return $this->getResult();
        } else {
            throw new AlreadyRunningTransactionException();
        }
    }

    /**
     * @return ApproveTransactionDto
     */
    abstract protected function run(): ApproveTransactionDto;

    /**
     * @return bool
     */
    private function isRunnable(): bool
    {
        return $this->redis->hsetnx($this->idempotency_id, 'status', self::STATUS_RUNNING) === 1;
    }

    private function setTtl(): void
    {
        // 최대 하루 동안 중복 결제 방지 보장
        $this->redis->expire($this->idempotency_id, TimeUnitConstant::SEC_IN_DAY);
    }

    /**
     * @return bool
     */
    private function isCompleted(): bool
    {
        return $this->redis->hget($this->idempotency_id, 'status') === self::STATUS_COMPLETED;
    }

    /**
     * @return ApproveTransactionDto
     */
    private function getResult(): ApproveTransactionDto
    {
        $content = json_decode($this->redis->hget($this->idempotency_id, 'result'));

        return new ApproveTransactionDto(
            $content->transaction_id,
            $content->partner_transaction_id,
            $content->product_name,
            (int) $content->amount,
            \DateTime::createFromFormat(DATE_ATOM, $content->reserved_at),
            \DateTime::createFromFormat(DATE_ATOM, $content->approved_at)
        );
    }

    /**
     * @param \JsonSerializable $result
     */
    private function setResult(\JsonSerializable $result): void
    {
        $this->redis->hmset(
            $this->idempotency_id,
            [
                'status' => self::STATUS_COMPLETED,
                'result' => json_encode($result)
            ]
        );
    }
}