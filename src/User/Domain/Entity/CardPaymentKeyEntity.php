<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Entity;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use RidiPay\Library\Crypto;
use RidiPay\Pg\Domain\Entity\PgEntity;
use RidiPay\User\Domain\PaymentMethodConstant;

/**
 * @Table(
 *   name="card_payment_key"
 * )
 * @Entity(repositoryClass="RidiPay\User\Domain\Repository\CardPaymentKeyRepository")
 */
class CardPaymentKeyEntity
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
     * @var CardEntity
     *
     * @ManyToOne(targetEntity="CardEntity", inversedBy="payment_keys", fetch="EAGER")
     * @JoinColumn(name="card_id", referencedColumnName="id", nullable=false)
     */
    private $card;

    /**
     * @var PgEntity
     *
     * @ManyToOne(targetEntity="RidiPay\Pg\Domain\Entity\PgEntity", fetch="EAGER")
     * @JoinColumn(name="pg_id", referencedColumnName="id", nullable=false)
     */
    private $pg;

    /**
     * @var string
     *
     * @Column(
     *   name="payment_key",
     *   type="string",
     *   length=191,
     *   nullable=false,
     *   options={
     *     "comment"="PG사에서 발급한 결제 key"
     *   }
     * )
     */
    private $payment_key;

    /**
     * @var string
     *
     * @Column(
     *   name="purpose",
     *   type="string",
     *   nullable=false,
     *   columnDefinition="ENUM('ONE_TIME','ONE_TIME_TAX_DEDUCTION','BILLING')",
     *   options={
     *     "default"="ONE_TIME",
     *     "comment"="용도(ONE_TIME: 소득 공제 불가능 단건 결제, ONE_TIME_TAX_DEDUCTION: 소득 공제 가능 단건 결제, BILLING: 정기 결제)"
     *   }
     * )
     */
    private $purpose;

    /**
     * @param CardEntity $card
     * @param PgEntity $pg
     * @param string $payment_key
     * @return CardPaymentKeyEntity
     * @throws \Exception
     */
    public static function createForOneTimePayment(
        CardEntity $card,
        PgEntity $pg,
        string $payment_key
    ) {
        return new CardPaymentKeyEntity(
            $card,
            $pg,
            $payment_key,
            PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_ONE_TIME
        );
    }

    /**
     * @param CardEntity $card
     * @param PgEntity $pg
     * @param string $payment_key
     * @return CardPaymentKeyEntity
     * @throws \Exception
     */
    public static function createForOneTimeTaxDeductionPayment(
        CardEntity $card,
        PgEntity $pg,
        string $payment_key
    ) {
        return new CardPaymentKeyEntity(
            $card,
            $pg,
            $payment_key,
            PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_ONE_TIME_TAX_DEDUCTION
        );
    }

    /**
     * @param CardEntity $card
     * @param PgEntity $pg
     * @param string $payment_key
     * @return CardPaymentKeyEntity
     * @throws \Exception
     */
    public static function createForBillingPayment(
        CardEntity $card,
        PgEntity $pg,
        string $payment_key
    ) {
        return new CardPaymentKeyEntity(
            $card,
            $pg,
            $payment_key,
            PaymentMethodConstant::CARD_PAYMENT_KEY_PURPOSE_BILLING
        );
    }

    /**
     * @param CardEntity $card
     * @param PgEntity $pg
     * @param string $payment_key
     * @param string $purpose
     * @throws \Exception
     */
    private function __construct(CardEntity $card, PgEntity $pg, string $payment_key, string $purpose)
    {
        $this->card = $card;
        $this->pg = $pg;
        $this->setEncryptedPaymentKey($payment_key);
        $this->purpose = $purpose;
    }

    /**
     * @return CardEntity
     */
    public function getCard(): CardEntity
    {
        return $this->card;
    }

    /**
     * @return PgEntity
     */
    public function getPg(): PgEntity
    {
        return $this->pg;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getPaymentKey(): string
    {
        return Crypto::decrypt($this->payment_key, self::getPaymentKeySecret());
    }

    /**
     * @param string $payment_key
     * @throws \Exception
     */
    private function setEncryptedPaymentKey(string $payment_key)
    {
        $this->payment_key = Crypto::encrypt($payment_key, self::getPaymentKeySecret());
    }

    /**
     * @return string
     */
    private static function getPaymentKeySecret(): string
    {
        return base64_decode(getenv('PAYMENT_KEY_SECRET', true));
    }
}
