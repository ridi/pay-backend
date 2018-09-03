<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

abstract class PgResponse
{
    /** @var bool */
    private $is_success;

    /** @var string */
    private $response_code;

    /** @var string */
    private $response_message;

    /**
     * @param bool $is_success
     * @param string $response_code
     * @param string $response_message
     */
    protected function __construct(bool $is_success, string $response_code, string $response_message)
    {
        $this->is_success = $is_success;
        $this->response_code = $response_code;
        $this->response_message = $response_message;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->is_success;
    }

    /**
     * @return string
     */
    public function getResponseCode(): string
    {
        return $this->response_code;
    }

    /**
     * @return string
     */
    public function getResponseMessage(): string
    {
        return $this->response_message;
    }
}
