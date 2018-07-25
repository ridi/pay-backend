<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="subscription", indexes={@ORM\Index(name="idx_pay_method_id", columns={"pay_method_id"}), @ORM\Index(name="idx_partner_id", columns={"partner_id"})})
 * @ORM\Entity
 */
class SubscriptionEntity
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
     * @ORM\Column(name="bill_key", type="string", length=255, nullable=false, options={"comment"="정기 결제 Bill Key"})
     */
    private $bill_key = '';

    /**
     * @var string
     *
     * @ORM\Column(name="purpose", type="string", length=32, nullable=false, options={"comment"="구독 목적"})
     */
    private $purpose;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created_at = 'CURRENT_TIMESTAMP';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="deleted_at", type="datetime", nullable=true)
     */
    private $deleted_at;
}
