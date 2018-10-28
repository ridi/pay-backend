<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Exception;

class TransactionCancellationException extends PgException
{
    /**
     * @param string $pg_message
     */
    public function __construct(string $pg_message)
    {
        $message = '결제 취소 중 오류가 발생했습니다.';

        parent::__construct($message, $pg_message);
    }
}
