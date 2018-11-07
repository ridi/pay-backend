<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Schema(
 *   schema="AlreadyApprovedTransaction",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="ALREADY_APPROVED_TRANSACTION"),
 *   @OA\Property(property="message", type="string", example="이미 승인된 결제입니다.")
 * )
 * @OA\Schema(
 *   schema="AlreadyCancelledTransaction",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="ALREADY_CANCELLED_TRANSACTION"),
 *   @OA\Property(property="message", type="string", example="이미 취소된 결제입니다.")
 * )
 * @OA\Schema(
 *   schema="AlreadyResumedSubscription",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="ALREADY_RESUMED_SUBSCRIPTION"),
 *   @OA\Property(property="message", type="string", example="이미 해지 취소된 구독입니다.")
 * )
 * @OA\Schema(
 *   schema="NotFoundSubscription",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="NOT_FOUND_SUBSCRIPTION"),
 *   @OA\Property(property="message", type="string", example="구독 내역을 찾을 수 없습니다.")
 * )
 * @OA\Schema(
 *   schema="NotFoundTransaction",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="NOT_FOUND_TRANSACTION"),
 *   @OA\Property(property="message", type="string", example="결제 내역을 찾을 수 없습니다.")
 * )
 * @OA\Schema(
 *   schema="NotReservedTransaction",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="NOT_RESERVED_TRANSACTION"),
 *   @OA\Property(property="message", type="string", example="올바르지 않은 결제 요청입니다.")
 * )
 * @OA\Schema(
 *   schema="UnvalidatedTransaction",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="UNVALIDATED_TRANSACTION"),
 *   @OA\Property(property="message", type="string", example="인증이 완료되지 않았습니다.")
 * )
 */
class TransactionErrorCodeConstant
{
    public const ALREADY_APPROVED_TRANSACTION = 'ALREADY_APPROVED_TRANSACTION';
    public const ALREADY_CANCELLED_TRANSACTION = 'ALREADY_CANCELLED_TRANSACTION';
    public const ALREADY_RESUMED_SUBSCRIPTION = 'ALREADY_RESUMED_SUBSCRIPTION';
    public const NOT_FOUND_SUBSCRIPTION = 'NOT_FOUND_SUBSCRIPTION';
    public const NOT_FOUND_TRANSACTION = 'NOT_FOUND_TRANSACTION';
    public const NOT_RESERVED_TRANSACTION = 'NOT_RESERVED_TRANSACTION';
    public const UNVALIDATED_TRANSACTION = 'UNVALIDATED_TRANSACTION';

    public const HTTP_STATUS_CODES = [
        self::ALREADY_APPROVED_TRANSACTION => Response::HTTP_FORBIDDEN,
        self::ALREADY_CANCELLED_TRANSACTION => Response::HTTP_FORBIDDEN,
        self::ALREADY_RESUMED_SUBSCRIPTION => Response::HTTP_FORBIDDEN,
        self::NOT_FOUND_SUBSCRIPTION => Response::HTTP_NOT_FOUND,
        self::NOT_FOUND_TRANSACTION => Response::HTTP_NOT_FOUND,
        self::NOT_RESERVED_TRANSACTION => Response::HTTP_NOT_FOUND,
        self::UNVALIDATED_TRANSACTION => Response::HTTP_FORBIDDEN
    ];
}
