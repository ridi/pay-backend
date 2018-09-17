<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Exception;

class CardRegistrationException extends \Exception
{
    /**
     * @param string $pg_response_message
     */
    public function __construct(string $pg_response_message)
    {
        $message = "카드 등록 중 오류가 발생했습니다. ({$pg_response_message})";

        parent::__construct($message);
    }
}
