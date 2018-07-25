<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="credit_card_issuer", indexes={@ORM\Index(name="idx_pg_id", columns={"pg_id"})})
 * @ORM\Entity
 */
class CreditCardIssuerEntity
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
     * @ORM\Column(name="code", type="string", length=16, nullable=false, options={"comment"="카드 발급사 코드"})
     */
    private $code = '';

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=16, nullable=false, options={"comment"="카드 발급사 이름"})
     */
    private $name = '';
}
