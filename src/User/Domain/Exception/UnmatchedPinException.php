<?php
declare(strict_types=1);

namespace RidiPay\User\Domain\Exception;

class UnmatchedPinException extends \Exception
{
    /**
     * @param int $remained_try_count
     */
    public function __construct(int $remained_try_count)
    {
        parent::__construct("비밀번호가 일치하지 않습니다. ({$remained_try_count}회 남음)");
    }
}
