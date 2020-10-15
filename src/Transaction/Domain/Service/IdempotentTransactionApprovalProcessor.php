<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Service;

use Predis\Client;
use RidiPay\Library\DuplicatedRequestException;
use RidiPay\Library\TimeUnitConstant;

abstract class IdempotentTransactionApprovalProcessor
{
    /** @var string 중복 결제 승인 방지를 위한 identifier */
    private $idempotency_id;

    /** @var Client */
    private $redis;

    /**
     * @param string $idempotency_id
     */
    public function __construct(string $idempotency_id)
    {
        $this->idempotency_id = hash('sha256', $idempotency_id);
        $this->redis = new Client(['host' => getenv('REDIS_HOST', true)]);
    }

    /**
     * pessimistic lock 방식 이용
     * lock를 획득한 경우에만 request를 처리할 수 있다.
     *
     * @throws DuplicatedRequestException
     * @throws \Throwable
     */
    public function process()
    {
        if ($this->lock()) {
            $this->setTtl();

            try {
                $result = $this->run();
                $this->setResult($result);

                return $result;
            } catch (\Throwable $t) {
                $this->unlock();

                throw $t;
            }
        } elseif ($this->isCompleted()) {
            return $this->getResult();
        } else {
            throw new DuplicatedRequestException();
        }
    }

    abstract protected function run(): TransactionApprovalResult;

    abstract protected function getResult(): TransactionApprovalResult;

    /**
     * @return bool
     */
    private function lock(): bool
    {
        return $this->redis->hsetnx($this->idempotency_id, 'lock', 1) === 1;
    }

    /**
     * @return bool
     */
    private function unlock(): bool
    {
        return $this->redis->hdel($this->idempotency_id, ['lock']) === 1;
    }

    private function setTtl(): void
    {
        // 최대 하루 동안 중복 요청 처리 방지 보장
        $this->redis->expire($this->idempotency_id, TimeUnitConstant::SEC_IN_DAY);
    }

    /**
     * @return bool
     */
    private function isCompleted(): bool
    {
        return $this->redis->hexists($this->idempotency_id, 'result') === 1;
    }

    /**
     * @param TransactionApprovalResult $result
     */
    private function setResult(TransactionApprovalResult $result): void
    {
        $this->redis->hset($this->idempotency_id, 'result', json_encode($result));
        // 결제 취소 시 transaction_id로 idempotency_id를 알아낸 후 저장된 result 삭제할 수 있도록 설정
        $this->redis->setex($result->transaction_id, TimeUnitConstant::SEC_IN_DAY, $this->idempotency_id);
    }

    /**
     * @return string
     */
    protected function getSerializedResult(): string
    {
        return $this->redis->hget($this->idempotency_id, 'result');
    }

    /**
     * @param string $transaction_uuid
     */
    public static function deleteResultIfExist(string $transaction_uuid)
    {
        $redis = new Client(['host' => getenv('REDIS_HOST', true)]);
        /** @var string|null $idempotency_id */
        $idempotency_id = $redis->get($transaction_uuid);
        if ($idempotency_id !== null) {
            $redis->del([$idempotency_id]);
        }
    }
}
