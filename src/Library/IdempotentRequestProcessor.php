<?php
declare(strict_types=1);

namespace RidiPay\Library;

use Predis\Client;

abstract class IdempotentRequestProcessor
{
    private const STATUS_RUNNING = 'RUNNING';
    private const STATUS_COMPLETED = 'COMPLETED';

    /** @var string 중복 Request 방지를 위한 identifier */
    private $request_id;

    /** @var Client */
    private $redis;

    /**
     * @param string $request_id
     */
    public function __construct(string $request_id)
    {
        $this->request_id = hash('sha256', $request_id);
        $this->redis = new Client(['host' => getenv('REDIS_HOST')]);
    }

    /**
     * @throws DuplicatedRequestException
     */
    public function process()
    {
        if ($this->isRunnable()) {
            $this->setTtl();

            $result = $this->run();
            $this->setResult($result);

            return $result;
        } elseif ($this->isCompleted()) {
            return $this->getResult();
        } else {
            throw new DuplicatedRequestException();
        }
    }

    abstract protected function run();

    abstract protected function getResult();

    /**
     * @return bool
     */
    private function isRunnable(): bool
    {
        return $this->redis->hsetnx($this->request_id, 'status', self::STATUS_RUNNING) === 1;
    }

    private function setTtl(): void
    {
        // 최대 하루 동안 중복 요청 처리 방지 보장
        $this->redis->expire($this->request_id, TimeUnitConstant::SEC_IN_DAY);
    }

    /**
     * @return bool
     */
    private function isCompleted(): bool
    {
        return $this->redis->hget($this->request_id, 'status') === self::STATUS_COMPLETED;
    }

    /**
     * @param \JsonSerializable $result
     */
    private function setResult(\JsonSerializable $result): void
    {
        $this->redis->hmset(
            $this->request_id,
            [
                'status' => self::STATUS_COMPLETED,
                'result' => json_encode($result)
            ]
        );
    }

    /**
     * @return string
     */
    protected function getSerializedResult(): string
    {
        return $this->redis->hget($this->request_id, 'result');
    }
}
