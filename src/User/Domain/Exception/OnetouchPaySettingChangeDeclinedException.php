<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class OnetouchPaySettingChangeDeclinedException extends \Exception
{
    /**
     * @param string $message
     */
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
