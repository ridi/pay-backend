<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Entity;

use RidiPay\User\Domain\Exception\UnavailableCardPurposeException;

/**
 * @Table(name="card", indexes={@Index(name="idx_payment_method_id", columns={"payment_method_id"}), @Index(name="idx_pg_id", columns={"pg_id"}), @Index(name="idx_card_issuer_id", columns={"card_issuer_id"})})
 * @Entity(repositoryClass="RidiPay\User\Domain\Repository\CardRepository")
 */
class CardEntity
{
    private const PURPOSE_ONE_TIME = 'ONE_TIME'; // 단건 결제
    private const PURPOSE_BILLING = 'BILLING'; // 정기 결제

    private const AVAILABLE_PURPOSES = [
        self::PURPOSE_ONE_TIME,
        self::PURPOSE_BILLING
    ];

    /**
     * @var int
     *
     * @Column(name="id", type="integer", nullable=false, options={"unsigned"=true})
     * @Id
     * @GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var PaymentMethodEntity
     *
     * @ManyToOne(targetEntity="RidiPay\User\Domain\Entity\PaymentMethodEntity", inversedBy="cards")
     * @JoinColumn(name="payment_method_id", referencedColumnName="id", nullable=false)
     */
    private $payment_method;

    /**
     * @var CardIssuerEntity
     *
     * @ManyToOne(targetEntity="RidiPay\User\Domain\Entity\CardIssuerEntity")
     * @JoinColumn(name="card_issuer_id", referencedColumnName="id", nullable=false)
     */
    private $card_issuer;

    /**
     * @var int
     *
     * @Column(name="pg_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="pg.id"})
     */
    private $pg_id;

    /**
     * @var string
     *
     * @Column(name="pg_bill_key", type="string", length=255, nullable=false, options={"comment"="PG사에서 발급한 bill key"})
     */
    private $pg_bill_key;

    /**
     * @var string
     *
     * @Column(name="hashed_card_number", type="string", length=255, nullable=false)
     */
    private $hashed_card_number;

    /**
     * @var string
     *
     * @Column(name="iin", type="string", length=255, nullable=false, options={"comment"="Issuer Identification Number(카드 번호 앞 6자리)"})
     */
    private $iin;

    /**
     * @var string
     *
     * @Column(name="purpose", type="string", nullable=false, columnDefinition="ENUM('ONE_TIME','BILLING')", options={"default"="ONE_TIME", "comment"="용도(ONE_TIME: 단건 결제, BILLING: 정기 결제)"})
     */
    private $purpose;

    /**
     * @param PaymentMethodEntity $payment_method
     * @param CardIssuerEntity $card_issuer
     * @param int $pg_id
     * @param string $pg_bill_key
     * @param string $card_number
     * @return CardEntity
     * @throws UnavailableCardPurposeException
     */
    public static function createForOneTimePayment(
        PaymentMethodEntity $payment_method,
        CardIssuerEntity $card_issuer,
        int $pg_id,
        string $pg_bill_key,
        string $card_number
    ): CardEntity {
        return new self($payment_method, $card_issuer, $pg_id, $pg_bill_key, $card_number, self::PURPOSE_ONE_TIME);
    }

    /**
     * @param PaymentMethodEntity $payment_method
     * @param CardIssuerEntity $card_issuer
     * @param int $pg_id
     * @param string $pg_bill_key
     * @param string $card_number
     * @return CardEntity
     * @throws UnavailableCardPurposeException
     */
    public static function createForBillingPayment(
        PaymentMethodEntity $payment_method,
        CardIssuerEntity $card_issuer,
        int $pg_id,
        string $pg_bill_key,
        string $card_number
    ): CardEntity {
        return new self($payment_method, $card_issuer, $pg_id, $pg_bill_key, $card_number, self::PURPOSE_BILLING);
    }

    /**
     * @param PaymentMethodEntity $payment_method
     * @param CardIssuerEntity $card_issuer,
     * @param int $pg_id
     * @param string $pg_bill_key
     * @param string $card_number
     * @param string $purpose
     * @throws UnavailableCardPurposeException
     */
    private function __construct(
        PaymentMethodEntity $payment_method,
        CardIssuerEntity $card_issuer,
        int $pg_id,
        string $pg_bill_key,
        string $card_number,
        string $purpose
    ) {
        $this->payment_method = $payment_method;
        $this->card_issuer = $card_issuer;
        $this->pg_id = $pg_id;
        $this->pg_bill_key = $pg_bill_key; // TODO: 암호화
        $this->hashed_card_number = self::hashCardNumber($card_number);
        $this->iin = substr($card_number, 0, 6); // TODO: 암호화
        $this->setPurpose($purpose);
    }

    /**
     * @return PaymentMethodEntity
     */
    public function getPaymentMethod(): PaymentMethodEntity
    {
        return $this->payment_method;
    }

    /**
     * @return CardIssuerEntity
     */
    public function getCardIssuer(): CardIssuerEntity
    {
        return $this->card_issuer;
    }

    /**
     * @return string
     */
    public function getPgBillKey(): string
    {
        return $this->pg_bill_key;
    }

    /**
     * @param string $card_number
     * @return bool
     */
    public function isSameCard(string $card_number): bool
    {
        return $this->hashed_card_number === self::hashCardNumber($card_number);
    }

    /**
     * @param string $card_number
     * @return string
     */
    private static function hashCardNumber(string $card_number): string
    {
        return hash('sha256', $card_number);
    }

    /**
     * @return string
     */
    public function getIin(): string
    {
        return $this->iin;
    }

    /**
     * @return bool
     */
    public function isAvailableOnOneTimePayment(): bool
    {
        return $this->purpose === self::PURPOSE_ONE_TIME;
    }

    /**
     * @return bool
     */
    public function isAvailableOnBillingPayment(): bool
    {
        return $this->purpose === self::PURPOSE_BILLING;
    }

    /**
     * @param string $purpose
     * @throws UnavailableCardPurposeException
     */
    private function setPurpose(string $purpose): void
    {
        if (!in_array($purpose, self::AVAILABLE_PURPOSES)) {
            throw new UnavailableCardPurposeException();
        }

        $this->purpose = $purpose;
    }
}
