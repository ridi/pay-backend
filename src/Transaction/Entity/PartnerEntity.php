<?php

namespace RidiPay\Transaction\Entity;

/**
 * @Table(name="partner", uniqueConstraints={@UniqueConstraint(name="uniq_name", columns={"name"}), @UniqueConstraint(name="uniq_api_key", columns={"api_key"})})
 * @Entity(repositoryClass="RidiPay\Transaction\Repository\PartnerRepository")
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
     * @Column(name="name", type="string", length=16, nullable=false, options={"comment"="가맹점 관리자 로그인 Username"})
     */
    private $name;

    /**
     * @var string
     *
     * @Column(name="password", type="string", length=255, nullable=false, options={"charset"="utf8mb4", "collation"="utf8mb4_unicode_ci", "comment"="가맹점 관리자 로그인 Password"})
     */
    private $password;

    /**
     * @var string
     *
     * @Column(name="api_key", type="string", length=255, nullable=false, options={"comment"="API 연동 Key"})
     */
    private $api_key;

    /**
     * @var string
     *
     * @Column(name="secret_key", type="string", length=255, nullable=false, options={"charset"="utf8mb4", "collation"="utf8mb4_unicode_ci", "comment"="API 연동 Secret Key"})
     */
    private $secret_key;

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
     * @Column(name="updated_at", type="datetime", columnDefinition="DATETIME on update CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL"))
     */
    private $updated_at;

    /**
     * @param string $name
     * @param string $password
     * @param string $api_key
     * @param string $secret_key
     * @param bool $is_first_party
     */
    public function __construct(
        string $name,
        string $password,
        string $api_key,
        string $secret_key,
        bool $is_first_party
    ) {
        $this->name = $name;
        $this->password = hash('sha256', $password);
        $this->api_key = $api_key;
        $this->secret_key = hash('sha256', $secret_key);
        $this->is_valid = true;
        $this->is_first_party = $is_first_party;
        $this->updated_at = new \DateTime();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param string $password
     * @return bool
     */
    public function isValidPassword(string $password): bool
    {
        return $this->password === hash('sha256', $password);
    }

    /**
     * @param string $secret_key
     * @return bool
     */
    public function isValidSecretKey(string $secret_key): bool
    {
        return $this->secret_key === hash('sha256', $secret_key);
    }
}
