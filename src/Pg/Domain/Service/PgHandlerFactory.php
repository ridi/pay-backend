<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

use RidiPay\Pg\Domain\Exception\UnsupportedPgException;
use RidiPay\Pg\Domain\PgConstant;
use RidiPay\Pg\Infrastructure\KcpHandler;

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
