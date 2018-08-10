<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

class BatchOrderResponse extends Response
{
    /**
     * @return string 가맹점 주문 번호
     */
    public function getOrderNo(): string
    {
        return $this->response['order_no'];
    }

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
     * @return string 카드 매입사 코드
     */
    public function getAcquCd(): string
    {
        return $this->response['acqu_cd'];
    }

    /**
     * @return string 카드 매입사 이름(한글)
     */
    public function getAcquName(): string
    {
        return $this->response['acqu_name'];
    }

    /**
     * @return string 결제 완료 후 결제 건에 대한 고유 값, 결제 건 취소 시 이용
     */
    public function getTno(): string
    {
        return $this->response['tno'];
    }

    /**
     * @return int 총 결제 금액
     */
    public function getAmount(): int
    {
        return intval($this->response['amount']);
    }

    /**
     * @return int 카드 결제 금액
     */
    public function getCardMny(): int
    {
        return intval($this->response['card_mny']);
    }

    /**
     * @return int 비과세 금액
     */
    public function getResTaxMny(): int
    {
        return intval($this->response['res_tax_mny']);
    }

    /**
     * @return int 부가세 금액
     */
    public function getResVatMny(): int
    {
        return intval($this->response['res_vat_mny']);
    }

    /**
     * @return int 할부 개월 수(0 ~ 12, 0: 일시불)
     */
    public function getQuota(): int
    {
        return intval($this->response['quota']);
    }

    /**
     * @return \DateTime 결제 승인 시각
     */
    public function getAppTime(): \DateTime
    {
        return \DateTime::createFromFormat('YmdHis', $this->response['app_time']);
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
                'order_no' => $this->getOrderNo(),
                'card_cd' => $this->getCardCd(),
                'card_name' => $this->getCardName(),
                'acqu_cd' => $this->getAcquCd(),
                'acqu_name' => $this->getAcquName(),
                'tno' => $this->getTno(),
                'amount' => $this->getAmount(),
                'card_mny' => $this->getCardMny(),
                'res_tax_mny' => $this->getResTaxMny(),
                'res_vat_mny' => $this->getResVatMny(),
                'quota' => $this->getQuota(),
                'app_time' => $this->getAppTime()->format(DATE_ATOM)
            ]
        );
    }
}
