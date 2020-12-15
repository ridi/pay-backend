<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

/**
 * @Table(
 *   name="payment_method_card",
 *   indexes={
 *     @Index(name="idx_card_issuer_id", columns={"card_issuer_id"})
 *   }
 * )
 * @Entity()
 */
class NewCardEntity
{
    /**
     * @var int
     *
     * @Column(name="id", type="integer", nullable=false, options={"unsigned"=true})
     * @Id
     */
    protected $id;

    /**
     * @var CardIssuerEntity
     *
     * @ManyToOne(targetEntity="RidiPay\User\Domain\Entity\CardIssuerEntity")
     * @JoinColumn(name="card_issuer_id", referencedColumnName="id", nullable=false)
     */
    private $card_issuer;

    /**
     * @var string
     *
     * @Column(
     *   name="iin",
     *   type="string",
     *   length=6,
     *   nullable=false,
     *   options={
     *     "comment"="Issuer Identification Number(카드 번호 앞 6자리)"
     *   }
     * )
     */
    private $iin;

    /**
     * @var Collection
     *
     * @OneToMany(targetEntity="RidiPay\User\Domain\Entity\CardPaymentKeyEntity", mappedBy="card")
     */
    private $payment_keys;

    /**
     * @param int $id
     * @param CardIssuerEntity $card_issuer
     * @param string $iin
     */
    public function __construct(
        int $id,
        CardIssuerEntity $card_issuer,
        string $iin
    ) {
        $this->id = $id;
        $this->card_issuer = $card_issuer;
        $this->iin = $iin;
        $this->payment_keys = new ArrayCollection();
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
     * @throws \Exception
     */
    public function getIin(): string
    {
        return $this->iin;
    }

    /**
     * @param CardPaymentKeyEntity[] $payment_keys
     */
    public function setPaymentKeys(array $payment_keys): void
    {
        $this->payment_keys = new ArrayCollection($payment_keys);
    }
}
