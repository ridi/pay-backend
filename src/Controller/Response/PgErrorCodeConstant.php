<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use Symfony\Component\HttpFoundation\Response;

class PgErrorCodeConstant
{
    public const CARD_REGISTRATION_FAILED = 'CARD_REGISTRATION_FAILED';
    public const TRANSACTION_APPROVAL_FAILED = 'TRANSACTION_APPROVAL_FAILED';
    public const TRANSACTION_CANCELLATION_FAILED = 'TRANSACTION_CANCELLATION_FAILED';

    public const HTTP_STATUS_CODES = [
        self::CARD_REGISTRATION_FAILED => Response::HTTP_INTERNAL_SERVER_ERROR,
        self::TRANSACTION_APPROVAL_FAILED => Response::HTTP_INTERNAL_SERVER_ERROR,
        self::TRANSACTION_CANCELLATION_FAILED => Response::HTTP_INTERNAL_SERVER_ERROR
    ];
}
