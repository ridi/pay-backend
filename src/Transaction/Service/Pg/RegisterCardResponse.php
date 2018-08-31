<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Service\Pg;

class RegisterCardResponse extends PgResponse
{
    /** @var string */
    private $pg_bill_key;
    
    /** @var string */
    private $card_issuer_code;

    /**
     * @param bool $is_success
     * @param string $response_code
     * @param string $response_message
     * @param string $pg_bill_key
     * @param string $card_issuer_code
     */
    public function __construct(bool $is_success, string $response_code, string $response_message, string $pg_bill_key, string $card_issuer_code)
    {
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
}
