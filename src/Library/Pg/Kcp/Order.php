<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

class Order
{
    /** @var int KRW 최소결제금액 */
    public const GOOD_PRICE_KRW_MIN = 100;

    /** @var string */
    private $id;

    /** @var string */
    private $good_name;

    /** @var integer */
    private $good_price;

    /** @var string */
    private $buyer_name;

    /** @var string */
    private $buyer_email;

    /** @var string */
    private $buyer_tel1;

    /** @var string */
    private $buyer_tel2;

    /**
     * @param string $id 주문번호
     * @param string $good_name 상품명
     * @param int $good_price_krw 결제 금액 (KRW)
     * @param string $buyer_name 구매자 이름
     * @param string $buyer_email 구매자 이메일 (KCP 결제 메일 수신 주소)
     * @param string $buyer_tel1 구매자 전화번호
     * @param string $buyer_tel2 구매자 휴대폰 번호 (KCP 결제알리미 설정시 문자 수신 번호)
     * @throws UnderMinimumPaymentAmountException
     */
    public function __construct(
        string $id,
        string $good_name,
        int $good_price_krw,
        string $buyer_name,
        string $buyer_email,
        string $buyer_tel1,
        string $buyer_tel2
    ) {
        if ($good_price_krw < self::GOOD_PRICE_KRW_MIN) {
            throw new UnderMinimumPaymentAmountException();
        }

        $this->id = $id;
        $this->good_name = $good_name;
        $this->good_price = $good_price_krw;
        $this->buyer_name = $buyer_name;
        $this->buyer_email = $buyer_email;
        $this->buyer_tel1 = $buyer_tel1;
        $this->buyer_tel2 = $buyer_tel2;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getGoodName(): string
    {
        return $this->good_name;
    }

    /**
     * @return int
     */
    public function getGoodPrice(): int
    {
        return $this->good_price;
    }

    /**
     * @return string
     */
    public function getBuyerName(): string
    {
        return $this->buyer_name;
    }

    /**
     * @return string
     */
    public function getBuyerEmail(): string
    {
        return $this->buyer_email;
    }
}
