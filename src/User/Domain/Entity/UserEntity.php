<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Entity;

use RidiPay\User\Domain\Exception\UnchangedPinException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;

/**
 * @Table(name="user")
 * @Entity(repositoryClass="RidiPay\User\Domain\Repository\UserRepository")
 */
class UserEntity
{
    /**
     * @var int
     *
     * @Column(name="u_idx", type="integer", nullable=false, options={"comment"="RIDIBOOKS 유저 고유 번호"})
     * @Id
     */
    private $u_idx;

    /**
     * @var string|null
     *
     * @Column(name="pin", type="string", length=255, nullable=true, options={"comment"="결제 비밀번호"})
     */
    private $pin;

    /**
     * @var bool|null
     *
     * @Column(name="is_using_onetouch_pay", type="boolean", nullable=true, options={"comment"="원터치 결제 사용 여부"})
     */
    private $is_using_onetouch_pay;

    /**
     * @var \DateTime
     *
     * @Column(
     *   name="created_at",
     *   type="datetime",
     *   nullable=false,
     *   options={
     *     "default"="CURRENT_TIMESTAMP",
     *     "comment"="RIDI PAY 가입 시각(최초 결제 수단 등록일)"
     *   }
     * )
     */
    private $created_at;

    /**
     * @var \DateTime|null
     *
     * @Column(
     *   name="leaved_at",
     *   type="datetime",
     *   nullable=true,
     *   options={
     *     "comment"="회원 탈퇴로 인한 RIDI PAY 해지 시각"
     *   }
     * )
     */
    private $leaved_at;

    /**
     * @param int $u_idx
     * @throws \Exception
     */
    public function __construct(int $u_idx)
    {
        $this->u_idx = $u_idx;
        $this->pin = null;
        $this->is_using_onetouch_pay = null; // 신규 유저 생성 시 원터치 결제 미설정
        $this->created_at = new \DateTime();
        $this->leaved_at = null;
    }

    /**
     * @param string $pin
     * @return string
     * @throws WrongFormattedPinException
     */
    public static function createPin(string $pin): string
    {
        self::assertValidPin($pin);

        return self::hashPin($pin);
    }

    /**
     * @param string $pin
     */
    public function setPin(string $pin): void
    {
        $this->pin = $pin;
    }

    /**
     * @param string $pin
     * @throws UnchangedPinException
     * @throws WrongFormattedPinException
     */
    public function updatePin(string $pin): void
    {
        self::assertValidPin($pin);
        self::assertNewPin($pin, $this->pin);

        $this->pin = self::hashPin($pin);
    }

    public function deletePin(): void
    {
        $this->pin = null;
    }

    /**
     * @param string $pin
     * @throws WrongFormattedPinException
     */
    private static function assertValidPin(string $pin): void
    {
        if (!preg_match('/[0-9]{6}/', $pin)) {
            throw new WrongFormattedPinException();
        }
    }

    /**
     * @param string $pin
     * @param string $previous_pin
     * @throws UnchangedPinException
     */
    private static function assertNewPin(string $pin, string $previous_pin): void
    {
        if ($previous_pin === self::hashPin($pin)) {
            throw new UnchangedPinException();
        }
    }

    /**
     * @return bool
     */
    public function hasPin(): bool
    {
        return !is_null($this->pin);
    }

    /**
     * @param string $pin
     * @return bool
     */
    public function isPinMatched(string $pin): bool
    {
        return password_verify($pin, $this->pin);
    }

    /**
     * @param string $pin
     * @return string
     */
    private static function hashPin(string $pin)
    {
        return password_hash($pin, PASSWORD_DEFAULT);
    }

    public function deleteOnetouchPay(): void
    {
        $this->is_using_onetouch_pay = null;
    }

    /**
     * @return bool
     */
    public function isLeaved(): bool
    {
        return !is_null($this->leaved_at);
    }

    public function leave(): void
    {
        $this->leaved_at = new \DateTime();
    }
}
