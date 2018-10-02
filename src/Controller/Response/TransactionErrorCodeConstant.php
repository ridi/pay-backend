<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Schema(
 *   schema="NonexistentTransaction",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="NONEXISTENT_TRANSACTION"),
 *   @OA\Property(property="message", type="string", example="존재하지 않는 결제 내역에 대한 요청입니다.")
 * )
 * @OA\Schema(
 *   schema="NotReservedTransaction",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="NOT_RESERVED_TRANSACTION"),
 *   @OA\Property(property="message", type="string", example="예약되지 않은 결제 내역에 대한 요청입니다.")
 * )
 * @OA\Schema(
 *   schema="UnvalidatedTransaction",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="UNVALIDATED_TRANSACTION"),
 *   @OA\Property(property="message", type="string", example="인증이 완료되지 않은 결제 요청입니다.")
 * )
 */
class TransactionErrorCodeConstant
{
    public const NONEXISTENT_TRANSACTION = 'NONEXISTENT_TRANSACTION';
    public const NOT_RESERVED_TRANSACTION = 'NOT_RESERVED_TRANSACTION';
    public const UNVALIDATED_TRANSACTION = 'UNVALIDATED_TRANSACTION';

    public const HTTP_STATUS_CODES = [
        self::NONEXISTENT_TRANSACTION => Response::HTTP_NOT_FOUND,
        self::NOT_RESERVED_TRANSACTION => Response::HTTP_NOT_FOUND,
        self::UNVALIDATED_TRANSACTION => Response::HTTP_FORBIDDEN
    ];
}
