<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

class CancelTransactionResponse extends Response
{
    /** @var string 기취소된 신용카드 거래 취소요청 */
    public const ALREADY_CANCELLED = '8133';

    /**
     * @return bool
     */
    public function isAlreadyCancelled(): bool
    {
        return $this->getResCd() === self::ALREADY_CANCELLED;
    }

    /**
     * @return string 가맹점 주문 번호
     */
    public function getOrderNo(): string
    {
        return $this->response['order_no'];
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
     * @return \DateTime 결제 취소 시각
     */
    public function getCancTime(): \DateTime
    {
        return \DateTime::createFromFormat('YmdHis', $this->response['canc_time']);
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
                'amount' => $this->getAmount(),
                'card_mny' => $this->getCardMny(),
                'quota' => $this->getQuota(),
                'app_time' => $this->getAppTime()->format(DATE_ATOM),
                'canc_time' => $this->getCancTime()->format(DATE_ATOM)
            ]
        );
    }
}
