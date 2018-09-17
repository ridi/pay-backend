<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class CardAlreadyExistsException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '카드는 하나만 등록할 수 있습니다.')
    {
        parent::__construct($message);
    }
}
