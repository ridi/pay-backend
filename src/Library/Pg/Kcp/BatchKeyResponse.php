<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

class BatchKeyResponse extends Response
{
    /**
     * @return string 카드 발급사 코드
     */
    public function getCardCd(): string
    {
        return $this->response['card_cd'];
    }

    /**
     * @return string 카드 발급사 이름(한글)
     */
    public function getCardName(): string
    {
        return $this->response['card_name'];
    }

    /**
     * @return string Batch 결제 요청 Key
     */
    public function getBatchKey(): string
    {
        return $this->response['batch_key'];
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        $common_data = parent::jsonSerialize();

        return array_merge(
            $common_data,
            [
                'card_cd' => $this->getCardCd(),
                'card_name' => $this->getCardName(),
                'batch_key' => $this->getBatchKey()
            ]
        );
    }
}
