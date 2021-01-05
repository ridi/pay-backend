<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

class CardRegistrationResponse extends PgResponse
{
    private const KCP_RESPONSE_CODE_UNMATCHED_EXPIRATION_DATE = 'CC55'; // 유효기간 오류
    private const KCP_RESPONSE_CODE_UNMATCHED_PASSWORD = 'CC63'; // 비밀번호 오류
    private const KCP_RESPONSE_CODE_UNMATCHED_BIRTH_DATE = 'CC66'; // 생년월일 또는 사업자번호 불일치
    private const KCP_RESPONSE_CODE_FORBIDDEN_CARD = 'CC69'; // 인증오류 3회초과(당일거래 불가)

    /** @var null|string */
    private $payment_key;
    
    /** @var null|string */
    private $card_issuer_code;

    /**
     * @param bool $is_success
     * @param string $response_code
     * @param string $response_message
     * @param null|string $payment_key
     * @param null|string $card_issuer_code
     */
    public function __construct(
        bool $is_success,
        string $response_code,
        string $response_message,
        ?string $payment_key,
        ?string $card_issuer_code
    ) {
        parent::__construct($is_success, $response_code, $response_message);

        $this->payment_key = $payment_key;
        $this->card_issuer_code = $card_issuer_code;
    }

    /**
     * @return string
     */
    public function getPaymentKey(): string
    {
        return $this->payment_key;
    }

    /**
     * @return string
     */
    public function getCardIssuerCode(): string
    {
        return $this->card_issuer_code;
    }

    /**
     * @return bool
     */
    public function isUnmatchedCardInformation(): bool
    {
        return in_array(
            $this->getResponseCode(),
            [
                self::KCP_RESPONSE_CODE_UNMATCHED_EXPIRATION_DATE,
                self::KCP_RESPONSE_CODE_UNMATCHED_PASSWORD,
                self::KCP_RESPONSE_CODE_UNMATCHED_BIRTH_DATE,
                self::KCP_RESPONSE_CODE_FORBIDDEN_CARD
            ],
            true
        );
    }
}
