<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Entity;

use RidiPay\User\Domain\Exception\OnetouchPaySettingChangeDeclinedException;
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
     */
    public function __construct(int $u_idx)
    {
        $this->u_idx = $u_idx;
        $this->pin = null;
        $this->is_using_onetouch_pay = null;
        $this->created_at = new \DateTime();
        $this->leaved_at = null;
    }

    /**
     * @param string $pin
     * @throws WrongFormattedPinException
     */
    public function createPin(string $pin): void
    {
        self::assertValidPin($pin);

        $this->pin = self::hashPin($pin);
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

    /**
     * @return bool
     */
    public function hasOnetouchPaySetting(): bool
    {
        return !is_null($this->is_using_onetouch_pay);
    }

    /**
     * @return bool
     */
    public function isUsingOnetouchPay(): bool
    {
        return $this->is_using_onetouch_pay;
    }

    /**
     * @throws OnetouchPaySettingChangeDeclinedException
     */
    public function enableOnetouchPay(): void
    {
        if (!$this->hasPin()) {
            // 원터치 결제 활성화 시 결제 비밀번호 소유 필수
            throw new OnetouchPaySettingChangeDeclinedException();
        }

        $this->is_using_onetouch_pay = true;
    }

    /**
     * @throws OnetouchPaySettingChangeDeclinedException
     */
    public function disableOnetouchPay(): void
    {
        if (!$this->hasPin()) {
            // 원터치 결제 비활성화 시 결제 비밀번호 소유 필수
            throw new OnetouchPaySettingChangeDeclinedException();
        }

        $this->is_using_onetouch_pay = false;
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
