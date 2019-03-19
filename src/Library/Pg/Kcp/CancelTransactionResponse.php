<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;
//TODO kcp api schema 맞출것
class CancelTransactionResponse extends Response
{
    /** @var string 기취소된 신용카드 거래 취소요청 */
    private const ALREADY_CANCELLED = '8133';

    /**
     * @return bool
     */
    public function isAlreadyCancelled(): bool
    {
        return $this->getResCd() === self::ALREADY_CANCELLED;
    }

    /**
     * @return string
     */
    public function getResEnMsg(): string
    {
        return $this->response['res_en_msg'];
    }

    /**
     * @return string
     */
    public function getTraceNo(): string
    {
        return $this->response['trace_no'];
    }

    /**
     * @return string
     */
    public function getPayMethod(): string
    {
        return $this->response['pay_method'];
    }

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
     * @return string
     */
    public function getMchtTaxno(): string
    {
        return $this->response['mcht_taxno'];
    }

    /**
     * @return string
     */
    public function getMallTaxno(): string
    {
        return $this->response['mall_taxno'];
    }

    /**
     * @return string 가맹점 주문 번호
     */
    public function getCaOrderId(): string
    {
        return $this->response['ca_order_id'];
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
     * @return int 쿠폰 결제 금액
     */
    public function getCouponMny(): int
    {
        return intval($this->response['coupon_mny']);
    }

    /**
     * @return bool
     */
    public function isEscwYn(): bool
    {
        return $this->response['escw_yn'] === 'Y';
    }

    /**
     * @return string 취소 구분값, 매입 취소와 승인 취소 구분
     */
    public function getCancGubn(): string
    {
        return $this->response['canc_gubn'];
    }

    /**
     * @return string
     */
    public function getVanCd(): string
    {
        return $this->response['van_cd'];
    }

    /**
     * @return \DateTime 결제 승인 시각
     */
    public function getAppTime(): \DateTime
    {
        return \DateTime::createFromFormat('YmdHis', $this->response['app_time']);
    }

    /**
     * @return \DateTime
     */
    public function getVanApptime(): \DateTime
    {
        return \DateTime::createFromFormat('YmdHis', $this->response['van_apptime']);
    }

    /**
     * @return \DateTime 결제 취소 시각
     */
    public function getCancTime(): \DateTime
    {
        return \DateTime::createFromFormat('YmdHis', $this->response['canc_time']);
    }

    /**
     * @return string 정상 결제 거래의 승인 번호, KCP와 카드사에서 공통적으로 관리하는 번호
     */
    public function getAppNo(): string
    {
        return $this->response['app_no'];
    }

    /**
     * @return string
     */
    public function getBizxNumb(): string
    {
        return $this->response['bizx_numb'];
    }

    /**
     * @return int 할부 개월 수(0 ~ 12, 0: 일시불)
     */
    public function getQuota(): int
    {
        return intval($this->response['quota']);
    }

    /**
     * @return bool 무이자 할부 결제 여부
     */
    public function isNoinf(): bool
    {
        return $this->response['noinf'] === 'Y';
    }

    /**
     * @return string
     */
    public function getPgTxid(): string
    {
        return $this->response['pg_txid'];
    }
}
