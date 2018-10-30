<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Exception;

use RidiPay\Pg\Domain\Service\CardRegistrationResponse;

class CardRegistrationException extends PgException
{
    /**
     * @param CardRegistrationResponse $response
     */
    public function __construct(CardRegistrationResponse $response)
    {
        $message = '카드 등록에 실패했습니다.';
        if ($response->isUnmatchedCardInformation()) {
            $message .= "\n3회 실패 시 당일 거래가 제한됩니다.";
        }

        parent::__construct($message, $response->getResponseMessage());
    }
}
