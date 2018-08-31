<?php

namespace RidiPay\User\Entity;

use RidiPay\User\Constant\CardPurposeConstant;
use RidiPay\Transaction\Entity\PgEntity;
use RidiPay\User\Exception\UnavailableCardPurposeException;

/**
 * @Table(name="card", indexes={@Index(name="idx_payment_method_id", columns={"payment_method_id"}), @Index(name="idx_pg_id", columns={"pg_id"}), @Index(name="idx_card_issuer_id", columns={"card_issuer_id"})})
 * @Entity(repositoryClass="RidiPay\User\Repository\CardRepository")
 */
class CardEntity
{
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
     * @ManyToOne(targetEntity="RidiPay\User\Entity\PaymentMethodEntity", inversedBy="cards")
     * @JoinColumn(name="payment_method_id", referencedColumnName="id", nullable=false)
     */
    private $payment_method;

    /**
     * @var PgEntity
     * @ManyToOne(targetEntity="RidiPay\Transaction\Entity\PgEntity")
     * @JoinColumn(name="pg_id", referencedColumnName="id", nullable=false)
     */
    private $pg;

    /**
     * @var CardIssuerEntity
     *
     * @ManyToOne(targetEntity="RidiPay\User\Entity\CardIssuerEntity")
     * @JoinColumn(name="card_issuer_id", referencedColumnName="id", nullable=false)
     */
    private $card_issuer;

    /**
     * @var string
     *
     * @Column(name="purpose", type="string", nullable=false, columnDefinition="ENUM('ONE_TIME','BILLING')", options={"default"="ONE_TIME", "comment"="용도(ONE_TIME: 단건 결제, BILLING: 정기 결제)"})
     */
    private $purpose;

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
     * @Column(name="pg_bill_key", type="string", length=255, nullable=false, options={"comment"="PG사에서 발급한 bill key"})
     */
    private $pg_bill_key;

    /**
     * @param PaymentMethodEntity $payment_method
     * @param PgEntity $pg
     * @param CardIssuerEntity $card_issuer
     * @param string $card_number
     * @param string $pg_bill_key
     * @return CardEntity
     */
    public static function createForOneTimePayment(
        PaymentMethodEntity $payment_method,
        PgEntity $pg,
        CardIssuerEntity $card_issuer,
        string $card_number,
        string $pg_bill_key
    ) {
        return new self($payment_method, $pg, $card_issuer, CardPurposeConstant::ONE_TIME, $card_number, $pg_bill_key);
    }

    /**
     * @param string $card_number
     * @param string $pg_bill_key
     * @param PaymentMethodEntity $payment_method
     * @param PgEntity $pg
     * @param CardIssuerEntity $card_issuer
     * @return CardEntity
     */
    public static function createForBillingPayment(
        PaymentMethodEntity $payment_method,
        PgEntity $pg,
        CardIssuerEntity $card_issuer,
        string $card_number,
        string $pg_bill_key
    ) {
        return new self($payment_method, $pg, $card_issuer, CardPurposeConstant::BILLING, $card_number, $pg_bill_key);
    }

    /**
     * @param PaymentMethodEntity $payment_method
     * @param PgEntity $pg
     * @param CardIssuerEntity $card_issuer
     * @param string $purpose
     * @param string $card_number
     * @param string $pg_bill_key
     * @throws UnavailableCardPurposeException
     */
    private function __construct(
        PaymentMethodEntity $payment_method,
        PgEntity $pg,
        CardIssuerEntity $card_issuer,
        string $purpose,
        string $card_number,
        string $pg_bill_key
    ) {
        self::assertValidPurpose($purpose);

        $this->payment_method = $payment_method;
        $this->pg = $pg;
        $this->card_issuer = $card_issuer;

        $this->purpose = $purpose;
        $this->hashed_card_number = self::hashCardNumber($card_number);
        $this->iin = substr($card_number, 0, 6); // TODO: 암호화
        $this->pg_bill_key = $pg_bill_key; // TODO: 암호화
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
     * @return bool
     */
    public function isAvailableOnOneTimePayment(): bool
    {
        return $this->purpose === CardPurposeConstant::ONE_TIME;
    }

    /**
     * @return bool
     */
    public function isAvailableOnBillingPayment(): bool
    {
        return $this->purpose === CardPurposeConstant::BILLING;
    }

    /**
     * @return string
     */
    public function getIin(): string
    {
        return $this->iin;
    }

    /**
     * @return string
     */
    public function getPgBillKey(): string
    {
        return $this->pg_bill_key;
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
     * @return PgEntity
     */
    public function getPg(): PgEntity
    {
        return $this->pg;
    }

    /**
     * @param string $purpose
     * @throws UnavailableCardPurposeException
     */
    private static function assertValidPurpose(string $purpose)
    {
        if (!in_array($purpose, CardPurposeConstant::AVAILABLE_PURPOSE)) {
            throw new UnavailableCardPurposeException();
        }
    }
}
