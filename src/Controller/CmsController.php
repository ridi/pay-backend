<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use RidiPay\Controller\Logger\ControllerAccessLogger;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Library\Jwt\Annotation\JwtAuth;
use RidiPay\Library\SentryHelper;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CmsController extends BaseController
{
    /**
     * @Route("/users/{u_idx}/cards/history", methods={"GET"}, requirements={"u_idx"="^\d+$"})
     * @JwtAuth()
     *
     * @OA\Get(
     *   path="/users/{u_idx}/cards/history",
     *   summary="카드 등록/삭제 이력 조회",
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
     * @param Request $request
     * @param int $u_idx
     * @return JsonResponse
     */
    public function getCardsHistory(Request $request, int $u_idx): JsonResponse
    {
        ControllerAccessLogger::logRequest($request);

        try {
            $cards_history = PaymentMethodAppService::getCardsHistory($u_idx);

            $response = self::createSuccessResponse($cards_history);
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            $response = self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }

    /**
     * @Route("/users/{u_idx}/onetouch/history", methods={"GET"}, requirements={"u_idx"="^\d+$"})
     * @JwtAuth()
     *
     * @OA\Get(
     *   path="/users/{u_idx}/onetouch/history",
     *   summary="원터치 결제 설정 변경 이력 조회",
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
     *       @OA\Items(ref="#/components/schemas/OnetouchPaySettingUpdateHistoryItemDto")
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
     * @param Request $request
     * @param int $u_idx
     * @return JsonResponse
     */
    public function getOnetouchPaySettingUpdateHistory(Request $request, int $u_idx): JsonResponse
    {
        ControllerAccessLogger::logRequest($request);

        try {
            $onetouch_pay_setting_update_history = UserAppService::getOnetouchPaySettingUpdateHistory($u_idx);

            $response = self::createSuccessResponse($onetouch_pay_setting_update_history);
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            $response = self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }

    /**
     * @Route("/users/{u_idx}/pin/history", methods={"GET"}, requirements={"u_idx"="^\d+$"})
     * @JwtAuth()
     *
     * @OA\Get(
     *   path="/users/{u_idx}/pin/history",
     *   summary="결제 비밀번호 변경 이력 조회",
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
     *       @OA\Items(ref="#/components/schemas/PinUpdateHistoryItemDto")
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
     * @param Request $request
     * @param int $u_idx
     * @return JsonResponse
     */
    public function getPinUpdateHistory(Request $request, int $u_idx): JsonResponse
    {
        ControllerAccessLogger::logRequest($request);

        try {
            $pin_update_history = UserAppService::getPinUpdateHistory($u_idx);

            $response = self::createSuccessResponse($pin_update_history);
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            $response = self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }
}