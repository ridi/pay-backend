<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class OnetouchPaySettingChangeDeclinedException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message = '결제 비밀번호를 설정해주세요.')
    {
        parent::__construct($message);
    }
}
