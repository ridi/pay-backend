<?php

namespace RidiPay\Transaction\Entity;

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
     * @var string
     *
     * @Column(name="bill_key", type="string", length=255, nullable=false, options={"comment"="정기 결제 Bill Key"})
     */
    private $bill_key = '';

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
    private $created_at = 'CURRENT_TIMESTAMP';

    /**
     * @var \DateTime|null
     *
     * @Column(name="deleted_at", type="datetime", nullable=true)
     */
    private $deleted_at;

    /** @var int
     *
     * @Column(name="payment_method_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="payment_method.id"})
     */
    private $payment_method_id;

    /** @var int
     *
     * @Column(name="partner_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="partner.id"})
     */
    private $partner_id;
}
