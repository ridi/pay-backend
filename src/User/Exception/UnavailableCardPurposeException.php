<?php
declare(strict_types=1);

namespace RidiPay\User\Exception;

class UnavailableCardPurposeException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '단건 또는 정기 결제 용도가 아닌 카드는 등록할 수 없습니다.')
    {
        parent::__construct($message);
    }
}
