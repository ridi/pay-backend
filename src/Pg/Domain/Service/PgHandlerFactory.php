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
                return KcpHandler::create();
            default:
                throw new UnsupportedPgException();
        }
    }

    /**
     * @param string $pg_name
     * @return KcpHandler
     * @throws UnsupportedPgException
     */
    public static function createWithTaxDeduction(string $pg_name): PgHandlerInterface
    {
        switch ($pg_name) {
            case PgConstant::KCP:
                return KcpHandler::createWithTaxDeduction();
            default:
                throw new UnsupportedPgException();
        }
    }

    /**
     * @param string $pg_name
     * @return PgHandlerInterface
     * @throws UnsupportedPgException
     */
    public static function createWithTest(string $pg_name): PgHandlerInterface
    {
        switch ($pg_name) {
            case PgConstant::KCP:
                return KcpHandler::createWithTest();
            default:
                throw new UnsupportedPgException();
        }
    }
}
