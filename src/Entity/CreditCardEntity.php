<?php

namespace App\Entity;

/**
 * @Table(name="credit_card", indexes={@Index(name="idx_pay_method_id", columns={"pay_method_id"}), @Index(name="idx_pg_id", columns={"pg_id"}), @Index(name="issuer_id", columns={"issuer_id"})})
 * @Entity
 */
class CreditCardEntity
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
     * @var string
     *
     * @Column(name="purpose", type="string", length=0, nullable=false, columnDefinition="ENUM('ONE_TIME','BILLING')", options={"default"="ONE_TIME","comment"="용도(ONE_TIME: 단건 결제, BILLING: 정기 결제)"})
     */
    private $purpose = 'ONE_TIME';

    /**
     * @var string
     *
     * @Column(name="iin", type="string", length=255, nullable=false, options={"comment"="Issuer Identification Number(카드 번호 앞 6자리)"})
     */
    private $iin = '';

    /**
     * @var string
     *
     * @Column(name="pg_bill_key", type="string", length=255, nullable=false, options={"comment"="PG사에서 발급한 bill key"})
     */
    private $pg_bill_key = '';
}
