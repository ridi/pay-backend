<?php
declare(strict_types=1);

namespace RidiPay\Library\Pg\Kcp;

class Card
{
    private const REGEX_NUMBER = '/^\d{13,16}$/';
    private const REGEX_EXPIRY = '/^\d{2}(0[1-9]|1[0-2])$/';
    private const REGEX_PASSWORD = '/^\d{2}$/';
    private const REGEX_TAX_ID = '/^(\d{2}(0[1-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1]))|\d{10}$/';

    /** @var string */
    private $number;

    /** @var string */
    private $expiry;

    /** @var string */
    private $password;

    /** @var string */
    private $tax_id;

    /**
     * @param string $number 4444555566667777 형태의 카드 번호
     * @param string $expiry YYMM 형태의 카드 유효기간 (ex. 2301)
     * @param string $password 카드 비밀번호 앞 두 자리
     * @param string $tax_id 개인인 경우 6자리 생년월일, 사업자인 경우 사업자등록번호
     */
    public function __construct(
        string $number,
        string $expiry,
        string $password,
        string $tax_id
    ) {
        self::validate($number, $expiry, $password, $tax_id);

        $this->number = $number;
        $this->expiry = $expiry;
        $this->password = $password;
        $this->tax_id = $tax_id;
    }

    /**
     * @param string $number
     * @param string $expiry
     * @param string $password
     * @param string $tax_id
     * @throws \InvalidArgumentException
     */
    private static function validate(string $number, string $expiry, string $password, string $tax_id)
    {
        if (!preg_match(self::REGEX_NUMBER, $number)
            || !preg_match(self::REGEX_EXPIRY, $expiry)
            || !preg_match(self::REGEX_PASSWORD, $password)
            || !preg_match(self::REGEX_TAX_ID, $tax_id)
        ) {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * KCP 결제 모듈의 호출 파라미터로 사용 가능한 형태의 문자열로 인코딩.
     *
     * @return string
     */
    public function __toString(): string
    {
        return Util::flattenAssocArray([
            'card_no' => $this->number,
            'card_expiry' => $this->expiry,
            'card_taxno' => $this->tax_id,
            'card_pwd' => $this->password,
        ], "\x1f", true);
    }
}
