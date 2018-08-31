<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Service\Pg;

use RidiPay\Transaction\Constant\PgConstant;
use RidiPay\Transaction\Exception\UnsupportedPgException;

class PgHandlerFactory
{
    /**
     * @param string $pg_type
     * @param bool $is_test
     * @return PgHandlerInterface
     * @throws UnsupportedPgException
     */
    public static function create(string $pg_type, bool $is_test): PgHandlerInterface
    {
        switch ($pg_type) {
            case PgConstant::KCP:
                return new KcpHandler($is_test);
            default:
                throw new UnsupportedPgException();
        }
    }
}
