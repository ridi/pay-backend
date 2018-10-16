<?php
declare(strict_types=1);

namespace RidiPay\Partner\Domain\Entity;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use RidiPay\Library\Crypto;

/**
 * @Table(
 *   name="partner",
 *   uniqueConstraints={
 *     @UniqueConstraint(name="uniq_name", columns={"name"}),
 *     @UniqueConstraint(name="uniq_api_key", columns={"api_key"})
 *   }
 * )
 * @Entity(repositoryClass="RidiPay\Partner\Domain\Repository\PartnerRepository")
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
     * @Column(name="name", type="string", length=32, nullable=false, options={"comment"="가맹점 관리자 로그인 Username"})
     */
    private $name;

    /**
     * @var string
     *
     * @Column(
     *   name="password",
     *   type="string",
     *   length=255,
     *   nullable=false,
     *   options={
     *     "comment"="가맹점 관리자 로그인 Password"
     *   }
     * )
     */
    private $password;

    /**
     * @var UuidInterface
     *
     * @Column(name="api_key", type="uuid_binary", nullable=false, options={"comment"="API 연동 Key"})
     */
    private $api_key;

    /**
     * @var string
     *
     * @Column(name="secret_key", type="string", length=255, nullable=false, options={"comment"="API 연동 Secret Key"})
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
     * @Column(name="is_first_party", type="boolean", nullable=false, options={"comment"="First Party(RIDI) 파트너 여부"})
     */
    private $is_first_party;

    /**
     * @var \DateTime
     *
     * @Column(
     *   name="updated_at",
     *   type="datetime",
     *   columnDefinition="DATETIME on update CURRENT_TIMESTAMP DEFAULT CURRENT_TIMESTAMP NOT NULL"
     * )
     */
    private $updated_at;

    /**
     * @param string $name
     * @param string $password
     * @param bool $is_first_party
     * @throws \Exception
     */
    public function __construct(
        string $name,
        string $password,
        bool $is_first_party
    ) {
        $this->name = $name;
        $this->password = self::hashPassword($password);
        $this->api_key = Uuid::uuid4();
        $this->setEncryptedSecretKey();
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
        return password_verify($password, $this->password);
    }

    /**
     * @param string $password
     * @return string
     */
    private static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * @return UuidInterface
     */
    public function getApiKey(): UuidInterface
    {
        return $this->api_key;
    }

    /**
     * @param string $secret_key
     * @return bool
     * @throws \Exception
     */
    public function isValidSecretKey(string $secret_key): bool
    {
        return $this->getSecretKey()->toString() === $secret_key;
    }

    /**
     * @return UuidInterface
     * @throws \Exception
     */
    public function getSecretKey(): UuidInterface
    {
        return Uuid::fromBytes(Crypto::decrypt($this->secret_key, self::getPartnerSecretKeySecret()));
    }

    private function setEncryptedSecretKey(): void
    {
        $this->secret_key = Crypto::encrypt(Uuid::uuid4()->getBytes(), self::getPartnerSecretKeySecret());
    }

    /**
     * @return string
     */
    private static function getPartnerSecretKeySecret(): string
    {
        return base64_decode(getenv('PARTNER_SECRET_KEY_SECRET'));
    }
}
