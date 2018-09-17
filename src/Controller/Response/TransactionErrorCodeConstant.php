<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use Symfony\Component\HttpFoundation\Response;

class TransactionErrorCodeConstant
{
    public const NONEXISTENT_TRANSACTION = 'NONEXISTENT_TRANSACTION';
    public const NOT_OWNED_TRANSACTION = 'NOT_OWNED_TRANSACTION';
    public const NOT_RESERVED_TRANSACTION = 'NOT_RESERVED_TRANSACTION';
    public const UNAUTHORIZED_PARTNER = 'UNAUTHORIZED_PARTNER';

    public const HTTP_STATUS_CODES = [
        self::NONEXISTENT_TRANSACTION => Response::HTTP_NOT_FOUND,
        self::NOT_OWNED_TRANSACTION => Response::HTTP_FORBIDDEN,
        self::NOT_RESERVED_TRANSACTION => Response::HTTP_FORBIDDEN,
        self::UNAUTHORIZED_PARTNER => Response::HTTP_UNAUTHORIZED
    ];
}
