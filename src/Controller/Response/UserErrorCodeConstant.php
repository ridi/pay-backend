<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Schema(
 *   schema="CardAlreadyExists",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="CARD_ALREADY_EXISTS"),
 *   @OA\Property(property="message", type="string", example="카드는 하나만 등록할 수 있습니다.")
 * )
 * @OA\Schema(
 *   schema="LeavedUser",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="LEAVED_USER"),
 *   @OA\Property(property="message", type="string", example="탈퇴한 사용자입니다.")
 * )
 * @OA\Schema(
 *   schema="NotFoundUser",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="NOT_FOUND_USER"),
 *   @OA\Property(property="message", type="string", example="RIDI Pay 이용자가 아닙니다.")
 * )
 * @OA\Schema(
 *   schema="OnetouchPaySettingChangeDeclined",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="ONETOUCH_PAY_SETTING_CHANGE_DECLINED"),
 *   @OA\Property(property="message", type="string", example="결제 비밀번호를 설정해주세요.")
 * )
 * @OA\Schema(
 *   schema="PasswordEntryBlocked",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="PASSWORD_ENTRY_BLOCKED"),
 *   @OA\Property(property="message", type="string", example="비밀번호를 5회 잘못 입력하셔서 이용이 제한되었습니다.")
 * )
 * @OA\Schema(
 *   schema="PinEntryBlocked",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="PIN_ENTRY_BLOCKED"),
 *   @OA\Property(property="message", type="string", example="비밀번호를 5회 잘못 입력하셔서 이용이 제한되었습니다.")
 * )
 * @OA\Schema(
 *   schema="PasswordUnmatched",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="PASSWORD_UNMATCHED"),
 *   @OA\Property(property="message", type="string", example="계정 비밀번호를 올바르게 입력해주세요.")
 * )
 * @OA\Schema(
 *   schema="PinUnmatched",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="PIN_UNMATCHED"),
 *   @OA\Property(property="message", type="string", example="결제 비밀번호를 올바르게 입력해주세요.")
 * )
 * @OA\Schema(
 *   schema="UnauthorizedPinChange",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="UNAUTHORIZED_PIN_CHANGE"),
 *   @OA\Property(property="message", type="string", example="결제 비밀번호를 변경할 수 없습니다.")
 * )
 * @OA\Schema(
 *   schema="UnchangedPin",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="UNCHANGED_PIN"),
 *   @OA\Property(property="message", type="string", example="현재 비밀번호와 동일합니다.")
 * )
 * @OA\Schema(
 *   schema="UnregisteredPaymentMethod",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="UNREGISTERED_PAYMENT_METHOD"),
 *   @OA\Property(property="message", type="string", example="등록되지 않은 결제 수단입니다.")
 * )
 * @OA\Schema(
 *   schema="WrongFormattedPin",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="WRONG_FORMATTED_PIN"),
 *   @OA\Property(property="message", type="string", example="결제 비밀번호는 0 ~ 9 사이의 6자리 숫자로 입력해야합니다.")
 * )
 */
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
    public const UNAUTHORIZED_PIN_CHANGE = 'UNAUTHORIZED_PIN_CHANGE';
    public const UNCHANGED_PIN = 'UNCHANGED_PIN';
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
        self::UNAUTHORIZED_PIN_CHANGE => Response::HTTP_UNAUTHORIZED,
        self::UNCHANGED_PIN => Response::HTTP_BAD_REQUEST,
        self::UNREGISTERED_PAYMENT_METHOD => Response::HTTP_NOT_FOUND,
        self::WRONG_FORMATTED_PIN => Response::HTTP_BAD_REQUEST
    ];
}
