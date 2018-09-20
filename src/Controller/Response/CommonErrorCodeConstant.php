<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use Symfony\Component\HttpFoundation\Response;

class CommonErrorCodeConstant
{
    public const INTERNAL_SERVER_ERROR = 'INTERNAL_SERVER_ERROR';

    public const HTTP_STATUS_CODES = [
        self::INTERNAL_SERVER_ERROR => Response::HTTP_INTERNAL_SERVER_ERROR
    ];
}
