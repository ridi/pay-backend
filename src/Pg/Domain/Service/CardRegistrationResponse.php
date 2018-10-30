<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

class CardRegistrationResponse extends PgResponse
{
    private const KCP_RESPONSE_CODE_UNMATCHED_EXPIRATION_DATE = 'CC55';
    private const KCP_RESPONSE_CODE_UNMATCHED_PASSWORD = 'CC63';
    private const KCP_RESPONSE_CODE_UNMATCHED_BIRTH_DATE = 'CC66';

    /** @var null|string */
    private $pg_bill_key;
    
    /** @var null|string */
    private $card_issuer_code;

    /**
     * @param bool $is_success
     * @param string $response_code
     * @param string $response_message
     * @param null|string $pg_bill_key
     * @param null|string $card_issuer_code
     */
    public function __construct(
        bool $is_success,
        string $response_code,
        string $response_message,
        ?string $pg_bill_key,
        ?string $card_issuer_code
    ) {
        parent::__construct($is_success, $response_code, $response_message);

        $this->pg_bill_key = $pg_bill_key;
        $this->card_issuer_code = $card_issuer_code;
    }

    /**
     * @return string
     */
    public function getPgBillKey(): string
    {
        return $this->pg_bill_key;
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
                self::KCP_RESPONSE_CODE_UNMATCHED_BIRTH_DATE
            ]
        );
    }
}
