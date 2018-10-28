<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Schema(
 *   schema="CardRegistrationFailed",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="CARD_REGISTRATION_FAILED"),
 *   @OA\Property(property="message", type="string", example="카드 등록 중 오류가 발생했습니다."),
 *   @OA\Property(property="pg_message", type="string", example="pg_response_message")
 * )
 * @OA\Schema(
 *   schema="TransactionApprovalFailed",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="TRANSACTION_APPROVAL_FAILED"),
 *   @OA\Property(property="message", type="string", example="결제 승인 중 오류가 발생했습니다."),
 *   @OA\Property(property="pg_message", type="string", example="pg_response_message")
 * )
 * @OA\Schema(
 *   schema="TransactionCancellationFailed",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="TRANSACTION_CANCELLATION_FAILED"),
 *   @OA\Property(property="message", type="string", example="결제 취소 중 오류가 발생했습니다."),
 *   @OA\Property(property="pg_message", type="string", example="pg_response_message")
 * )
 */
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
