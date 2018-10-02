<?php
declare(strict_types=1);

namespace RidiPay\Controller\Response;

use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Schema(
 *   schema="UnauthorizedPartner",
 *   type="object",
 *   required={"code", "message"},
 *   @OA\Property(property="code", type="string", example="UNAUTHORIZED_PARTNER"),
 *   @OA\Property(property="message", type="string", example="등록된 가맹점이 아니거나, 가맹점 연동 정보가 일치하지 않습니다.")
 * )
 */
class PartnerErrorCodeConstant
{
    public const UNAUTHORIZED_PARTNER = 'UNAUTHORIZED_PARTNER';

    public const HTTP_STATUS_CODES = [
        self::UNAUTHORIZED_PARTNER => Response::HTTP_UNAUTHORIZED
    ];
}
