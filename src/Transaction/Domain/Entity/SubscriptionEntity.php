<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Domain\Entity;

use RidiPay\User\Domain\Entity\PaymentMethodEntity;

/**
 * @Table(name="subscription", indexes={@Index(name="idx_payment_method_id", columns={"payment_method_id"}), @Index(name="idx_partner_id", columns={"partner_id"})})
 * @Entity
 */
class SubscriptionEntity
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
     * @ManyToOne(targetEntity="RidiPay\User\Domain\Entity\PaymentMethodEntity")
     * @JoinColumn(name="payment_method_id", referencedColumnName="id", nullable=false)
     */
    private $payment_method;

    /**
     * @var PartnerEntity
     *
     * @ManyToOne(targetEntity="RidiPay\Transaction\Domain\Entity\PartnerEntity")
     * @JoinColumn(name="partner_id", referencedColumnName="id", nullable=false)
     */
    private $partner;

    /**
     * @var string
     *
     * @Column(name="bill_key", type="string", length=255, nullable=false, options={"comment"="정기 결제 Bill Key"})
     */
    private $bill_key;

    /**
     * @var string
     *
     * @Column(name="purpose", type="string", length=32, nullable=false, options={"comment"="구독 목적"})
     */
    private $purpose;

    /**
     * @var \DateTime
     *
     * @Column(name="created_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $created_at;

    /**
     * @var \DateTime|null
     *
     * @Column(name="deleted_at", type="datetime", nullable=true)
     */
    private $deleted_at;
}