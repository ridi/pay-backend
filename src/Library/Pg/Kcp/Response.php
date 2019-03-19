<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;
//TODO kcp api schema 맞출것
abstract class Response
{
    /** @var string 정상처리 */
    private const OK = '0000';

    /** @var array */
    protected $response;

    /**
     * @param array $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->getResCd() === self::OK;
    }

    /**
     * @return array
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * @return string
     */
    public function getResCd(): string
    {
        return $this->response['res_cd'];
    }

    /**
     * @return string
     */
    public function getResMsg(): string
    {
        return $this->response['res_msg'];
    }
}
