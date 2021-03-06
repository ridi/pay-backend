<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Schema(
 *   schema="DuplicatedRequest",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="DUPLICATED_REQUEST"),
 *   @OA\Property(property="message", type="string", example="이미 처리 중인 요청입니다.")
 * )
 * @OA\Schema(
 *   schema="InvalidAccessToken",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="INVALID_ACCESS_TOKEN"),
 *   @OA\Property(property="message", type="string")
 * )
 * @OA\Schema(
 *   schema="InvalidContentType",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="INVALID_CONTENT_TYPE"),
 *   @OA\Property(property="message", type="string")
 * )
 * @OA\Schema(
 *   schema="InvalidJwt",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="INVALID_JWT"),
 *   @OA\Property(property="message", type="string")
 * )
 * @OA\Schema(
 *   schema="InvalidParameter",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="INVALID_PARAMETER"),
 *   @OA\Property(property="message", type="string")
 * )
 * @OA\Schema(
 *   schema="InvalidValidationToken",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="INVALID_VALIDATION_TOKEN"),
 *   @OA\Property(property="message", type="string")
 * )
 * @OA\Schema(
 *   schema="LoginRequired",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="LOGIN_REQUIRED"),
 *   @OA\Property(property="message", type="string")
 * )
 * @OA\Schema(
 *   schema="InternalServerError",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="INTERNAL_SERVER_ERROR"),
 *   @OA\Property(property="message", type="string")
 * )
 */
class CommonErrorCodeConstant
{
    public const DUPLICATED_REQUEST = 'DUPLICATED_REQUEST';
    public const INVALID_CONTENT_TYPE = 'INVALID_CONTENT_TYPE';
    public const INVALID_PARAMETER = 'INVALID_PARAMETER';
    public const INVALID_JWT = 'INVALID_JWT';
    public const INVALID_VALIDATION_TOKEN = 'INVALID_VALIDATION_TOKEN';
    public const INVALID_ACCESS_TOKEN = 'INVALID_ACCESS_TOKEN';
    public const LOGIN_REQUIRED = 'LOGIN_REQUIRED';
    public const INTERNAL_SERVER_ERROR = 'INTERNAL_SERVER_ERROR';

    public const HTTP_STATUS_CODES = [
        self::DUPLICATED_REQUEST => Response::HTTP_FORBIDDEN,
        self::INVALID_CONTENT_TYPE => Response::HTTP_BAD_REQUEST,
        self::INVALID_PARAMETER => Response::HTTP_BAD_REQUEST,
        self::INVALID_JWT => Response::HTTP_UNAUTHORIZED,
        self::INVALID_VALIDATION_TOKEN => Response::HTTP_UNAUTHORIZED,
        self::INVALID_ACCESS_TOKEN => Response::HTTP_UNAUTHORIZED,
        self::LOGIN_REQUIRED => Response::HTTP_UNAUTHORIZED,
        self::INTERNAL_SERVER_ERROR => Response::HTTP_INTERNAL_SERVER_ERROR
    ];
}
