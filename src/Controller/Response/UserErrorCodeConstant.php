<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Schema(
 *   schema="DeletedPaymentMethod",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="DELETED_PAYMENT_METHOD"),
 *   @OA\Property(property="message", type="string", example="삭제된 결제 수단입니다.")
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
 *   @OA\Property(property="message", type="string", example="이용자가 아닙니다.")
 * )
 * @OA\Schema(
 *   schema="PaymentMethodChangeDeclined",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="PAYMENT_METHOD_CHANGE_DECLINED"),
 *   @OA\Property(property="message", type="string", example="결제 시도 중에는 결제 수단을 변경할 수 없습니다. 잠시 후 다시 시도해주세요.")
 * )
 * @OA\Schema(
 *   schema="PinEntryBlocked",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="PIN_ENTRY_BLOCKED"),
 *   @OA\Property(property="message", type="string", example="비밀번호를 5회 잘못 입력하셔서 이용이 제한되었습니다.")
 * )
 * @OA\Schema(
 *   schema="PinUnmatched",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="PIN_UNMATCHED"),
 *   @OA\Property(property="message", type="string", example="결제 비밀번호를 올바르게 입력해주세요.")
 * )
 * @OA\Schema(
 *   schema="UnauthorizedCardRegistration",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="UNAUTHORIZED_CARD_REGISTRATION"),
 *   @OA\Property(property="message", type="string", example="카드를 등록할 수 없습니다.")
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
    public const DELETED_PAYMENT_METHOD = 'DELETED_PAYMENT_METHOD';
    public const LEAVED_USER = 'LEAVED_USER';
    public const NOT_FOUND_USER = 'NOT_FOUND_USER';
    public const PAYMENT_METHOD_CHANGE_DECLINED = 'PAYMENT_METHOD_CHNAGE_DECLINED';
    public const PIN_ENTRY_BLOCKED = 'PIN_ENTRY_BLOCKED';
    public const PIN_UNMATCHED = 'PIN_UNMATCHED';
    public const UNAUTHORIZED_CARD_REGISTRATION = 'UNAUTHORIZED_CARD_REGISTRATION';
    public const UNCHANGED_PIN = 'UNCHANGED_PIN';
    public const UNREGISTERED_PAYMENT_METHOD = 'UNREGISTERED_PAYMENT_METHOD';
    public const WRONG_FORMATTED_PIN = 'WRONG_FORMATTED_PIN';

    public const HTTP_STATUS_CODES = [
        self::DELETED_PAYMENT_METHOD => Response::HTTP_FORBIDDEN,
        self::LEAVED_USER => Response::HTTP_FORBIDDEN,
        self::NOT_FOUND_USER => Response::HTTP_NOT_FOUND,
        self::PAYMENT_METHOD_CHANGE_DECLINED => Response::HTTP_FORBIDDEN,
        self::PIN_ENTRY_BLOCKED => Response::HTTP_FORBIDDEN,
        self::PIN_UNMATCHED => Response::HTTP_BAD_REQUEST,
        self::UNAUTHORIZED_CARD_REGISTRATION => Response::HTTP_UNAUTHORIZED,
        self::UNCHANGED_PIN => Response::HTTP_BAD_REQUEST,
        self::UNREGISTERED_PAYMENT_METHOD => Response::HTTP_NOT_FOUND,
        self::WRONG_FORMATTED_PIN => Response::HTTP_BAD_REQUEST
    ];
}
