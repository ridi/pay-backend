<?php
declare(strict_types=1);

namespace RidiPay\Partner\Domain\Exception;

class NotFoundPartnerException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '가맹점을 찾을 수 없습니다.')
    {
        parent::__construct($message);
    }
}
