<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Library\Jwt\Annotation\JwtAuth;
use RidiPay\Library\SentryHelper;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class CmsController extends BaseController
{
    /**
     * @Route("/users/{u_idx}/payment-methods/history", methods={"GET"}, requirements={"u_idx"="\d+"})
     * @JwtAuth()
     *
     * @OA\Get(
     *   path="/users/{u_idx}/payment-methods/history",
     *   summary="결제 수단 등록/삭제 이력 조회",
     *   tags={"cms-api"},
     *   @OA\Parameter(
     *     name="u_idx",
     *     description="RIDIBOOKS 유저 고유 번호",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       @OA\Items(ref="#/components/schemas/CardHistoryItemDto")
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/InvalidJwt")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @param int $u_idx
     * @return JsonResponse
     */
    public function getPaymentMethodHistory(int $u_idx): JsonResponse
    {
        try {
            $payment_methods_history = PaymentMethodAppService::getPaymentMethodsHistory($u_idx);
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse($payment_methods_history);
    }
}
