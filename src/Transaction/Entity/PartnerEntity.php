<?php

namespace RidiPay\Transaction\Entity;

/**
 * @Table(name="partner", uniqueConstraints={@UniqueConstraint(name="uniq_secret", columns={"secret"}), @UniqueConstraint(name="uniq_name", columns={"name"})})
 * @Entity
 */
class PartnerEntity
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
     * @Column(name="name", type="string", length=16, nullable=false, options={"comment"="가맹점 식별을 위한 가맹점명"})
     */
    private $name = '';

    /**
     * @var string
     *
     * @Column(name="secret", type="string", length=255, nullable=false, options={"comment"="가맹점 연동을 위한 Secret"})
     */
    private $secret = '';

    /**
     * @var bool
     *
     * @Column(name="is_valid", type="boolean", nullable=false)
     */
    private $is_valid;

    /**
     * @var bool
     *
     * @Column(name="is_first_party", type="boolean", nullable=false, options={"comment"="First Party(RIDI) 파트너 여부 "})
     */
    private $is_first_party;

    /**
     * @var \DateTime
     *
     * @Column(name="updated_at", type="datetime", nullable=false, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $updated_at = 'CURRENT_TIMESTAMP';
}
