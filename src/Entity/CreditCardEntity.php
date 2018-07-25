<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="credit_card", indexes={@ORM\Index(name="idx_pay_method_id", columns={"pay_method_id"}), @ORM\Index(name="idx_pg_id", columns={"pg_id"}), @ORM\Index(name="issuer_id", columns={"issuer_id"})})
 * @ORM\Entity
 */
class CreditCardEntity
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false, options={"unsigned"=true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="purpose", type="string", length=0, nullable=false, columnDefinition="ENUM('ONE_TIME','BILLING')", options={"default"="ONE_TIME","comment"="용도(ONE_TIME: 단건 결제, BILLING: 정기 결제)"})
     */
    private $purpose = 'ONE_TIME';

    /**
     * @var string
     *
     * @ORM\Column(name="iin", type="string", length=255, nullable=false, options={"comment"="Issuer Identification Number(카드 번호 앞 6자리)"})
     */
    private $iin = '';

    /**
     * @var string
     *
     * @ORM\Column(name="pg_bill_key", type="string", length=255, nullable=false, options={"comment"="PG사에서 발급한 bill key"})
     */
    private $pg_bill_key = '';
}
