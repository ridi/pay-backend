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
     * @return string
     */
    public function getCardBankCd(): string
    {
        return $this->response['card_bank_cd'];
    }

    /**
     * @return string
     */
    public function getVanTxId(): string
    {
        return $this->response['van_tx_id'];
    }

    /**
     * @return string
     */
    public function getCardBinType01(): string
    {
        return $this->response['card_bin_type_01'];
    }

    /**
     * @return string Batch 결제 요청 Key
     */
    public function getBatchKey(): string
    {
        return $this->response['batch_key'];
    }

    /**
     * @return string
     */
    public function getJoinCd(): string
    {
        return $this->response['join_cd'];
    }

    /**
     * @return string 카드 발급사 이름(한글)
     */
    public function getCardName(): string
    {
        return $this->response['card_name'];
    }
}
