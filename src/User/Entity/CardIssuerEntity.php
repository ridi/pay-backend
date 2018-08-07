<?php

namespace RidiPay\User\Entity;

/**
 * @Table(name="card_issuer", indexes={@Index(name="idx_pg_id_code", columns={"pg_id", "code"})})
 * @Entity(repositoryClass="RidiPay\User\Repository\CardIssuerRepository")
 */
class CardIssuerEntity
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
     * @var int
     *
     * @Column(name="pg_id", type="integer", nullable=false, options={"unsigned"=true, "comment"="pg.id"})
     */
    private $pg_id;

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

    /**
     * @var string
     *
     * @Column(name="color", type="string", length=6, nullable=false, options={"comment"="카드 발급사 색상"})
     */
    private $color;

    /**
     * @var string
     *
     * @Column(name="logo_image_url", type="string", length=128, nullable=false, options={"comment"="카드 발급사 로고 이미지 URL"})
    */
    private $logo_image_url;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getColor(): string
    {
        return $this->color;
    }

    /**
     * @return string
     */
    public function getLogoImageUrl(): string
    {
        return $this->logo_image_url;
    }
}
