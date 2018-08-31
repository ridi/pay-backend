<?php
declare(strict_types=1);

namespace RidiPay\Transaction\Service\Pg;

use RidiPay\Transaction\Constant\PgConstant;
use RidiPay\Transaction\Exception\UnsupportedPgException;

class PgHandlerFactory
{
    /**
     * @param string $pg_name
     * @return PgHandlerInterface
     * @throws UnsupportedPgException
     */
    public static function create(string $pg_name): PgHandlerInterface
    {
        switch ($pg_name) {
            case PgConstant::KCP:
                return new KcpHandler();
            default:
                throw new UnsupportedPgException();
        }
    }
}
