<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use Symfony\Component\HttpFoundation\Response;

class UserErrorCodeConstant
{
    public const CARD_ALREADY_EXISTS = 'CARD_ALREADY_EXISTS';
    public const LEAVED_USER = 'LEAVED_USER';
    public const NOT_FOUND_USER = 'NOT_FOUND_USER';
    public const ONETOUCH_PAY_SETTING_CHANGE_DECLINED = 'ONETOUCH_PAY_SETTING_CHANGE_DECLINED';
    public const PASSWORD_ENTRY_BLOCKED = 'PASSWORD_ENTRY_BLOCKED';
    public const PIN_ENTRY_BLOCKED = 'PIN_ENTRY_BLOCKED';
    public const PASSWORD_UNMATCHED = 'PASSWORD_UNMATCHED';
    public const PIN_UNMATCHED = 'PIN_UNMATCHED';
    public const UNREGISTERED_PAYMENT_METHOD = 'UNREGISTERED_PAYMENT_METHOD';
    public const WRONG_FORMATTED_PIN = 'WRONG_FORMATTED_PIN';

    public const HTTP_STATUS_CODES = [
        self::CARD_ALREADY_EXISTS => Response::HTTP_FORBIDDEN,
        self::LEAVED_USER => Response::HTTP_FORBIDDEN,
        self::NOT_FOUND_USER => Response::HTTP_NOT_FOUND,
        self::ONETOUCH_PAY_SETTING_CHANGE_DECLINED => Response::HTTP_FORBIDDEN,
        self::PASSWORD_ENTRY_BLOCKED => Response::HTTP_FORBIDDEN,
        self::PIN_ENTRY_BLOCKED => Response::HTTP_FORBIDDEN,
        self::PASSWORD_UNMATCHED => Response::HTTP_BAD_REQUEST,
        self::PIN_UNMATCHED => Response::HTTP_BAD_REQUEST,
        self::UNREGISTERED_PAYMENT_METHOD => Response::HTTP_NOT_FOUND,
        self::WRONG_FORMATTED_PIN => Response::HTTP_BAD_REQUEST
    ];
}
