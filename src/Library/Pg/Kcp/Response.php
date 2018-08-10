<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

abstract class Response implements \JsonSerializable
{
    /** @var array */
    protected $response;

    /** @var string 정상처리 */
    public const OK = '0000';

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

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'is_success' => $this->isSuccess(),
            'res_cd' => $this->getResCd(),
            'res_msg' => $this->getResMsg()
        ];
    }
}
