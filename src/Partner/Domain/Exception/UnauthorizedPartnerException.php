<?php
declare(strict_types=1);

namespace RidiPay\Partner\Domain\Exception;

class UnauthorizedPartnerException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '등록된 가맹점이 아니거나, 가맹점 연동 정보가 일치하지 않습니다.')
    {
        parent::__construct($message);
    }
}
