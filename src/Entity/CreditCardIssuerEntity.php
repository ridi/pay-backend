<?php

namespace App\Entity;

/**
 * @Table(name="credit_card_issuer", indexes={@Index(name="idx_pg_id", columns={"pg_id"})})
 * @Entity
 */
class CreditCardIssuerEntity
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
     * @Column(name="code", type="string", length=16, nullable=false, options={"comment"="카드 발급사 코드"})
     */
    private $code = '';

    /**
     * @var string
     *
     * @Column(name="name", type="string", length=16, nullable=false, options={"comment"="카드 발급사 이름"})
     */
    private $name = '';
}
