<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Cors\Annotation\Cors;
use RidiPay\Library\Jwt\Annotation\JwtAuth;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use RidiPay\User\Domain\Exception\PasswordEntryBlockedException;
use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\OnetouchPaySettingChangeDeclinedException;
use RidiPay\User\Domain\Exception\UnmatchedPasswordException;
use RidiPay\User\Domain\Exception\UnmatchedPinException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends BaseController
{
    /**
     * @Route("/users/{u_idx}", methods={"DELETE"}, requirements={"u_idx"="\d+"})
     * @JwtAuth()
     *
     * @OA\Delete(
     *   path="/users/{u_idx}",
     *   summary="서점 회원 탈퇴 시, RIDI Pay 탈퇴 처리",
     *   tags={"private-api-for-first-party"},
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
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/InvalidJwt")
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/LeavedUser")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundUser")
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
    public function deleteUser(int $u_idx): JsonResponse
    {
        try {
            UserAppService::deleteUser($u_idx);
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse();
    }

    /**
     * @Route("/users/{u_idx}/payment-methods", methods={"GET"}, requirements={"u_idx"="\d+"})
     * @JwtAuth()
     *
     * @OA\Get(
     *   path="/users/{u_idx}/payment-methods",
     *   summary="등록된 결제 수단 목록 조회",
     *   tags={"private-api-for-first-party"},
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
     *     @OA\JsonContent(ref="#/components/schemas/AvailablePaymentMethodsDto")
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
    public function getPaymentMethods(int $u_idx): JsonResponse
    {
        try {
            $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
            foreach ($payment_methods->cards as $card) {
                unset($card->color);
                unset($card->logo_image_url);
                unset($card->subscriptions);
            }
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse(['cards' => $payment_methods->cards]);
    }

    /**
     * @Route("/me", methods={"OPTIONS"})
     * @Cors(methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getMyInformationPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me", methods={"GET"})
     * @OAuth2()
     *
     * @OA\Get(
     *   path="/me",
     *   summary="RIDI Pay 유저 정보 조회",
     *   tags={"private-api"},
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(ref="#/components/schemas/UserInformationDto")
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidAccessToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/LeavedUser")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundUser")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @return JsonResponse
     */
    public function getMyInformation(): JsonResponse
    {
        try {
            $user_information = UserAppService::getUserInformation($this->getUidx());
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse([
            'payment_methods' => $user_information->payment_methods,
            'has_pin' => $user_information->has_pin,
            'is_using_onetouch_pay' => $user_information->is_using_onetouch_pay
        ]);
    }

    /**
     * @Route("/me/pin", methods={"OPTIONS"})
     * @Cors(methods={"PUT"})
     *
     * @return JsonResponse
     */
    public function updatePinPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/pin", methods={"PUT"})
     * @ParamValidator({"param"="pin", "constraints"={{"Regex"="/\d{6}/"}}})
     * @OAuth2()
     *
     * @OA\Put(
     *   path="/me/pin",
     *   summary="결제 비밀번호 변경",
     *   tags={"private-api"},
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"pin"},
     *       @OA\Property(property="pin", type="string", description="결제 비밀번호", example="123456")
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidParameter"),
     *         @OA\Schema(ref="#/components/schemas/WrongFormattedPin")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidAccessToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/LeavedUser")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundUser")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePin(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            UserAppService::updatePin($this->getUidx(), $body->pin);
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (WrongFormattedPinException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::WRONG_FORMATTED_PIN,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/pin/validate", methods={"OPTIONS"})
     * @Cors(methods={"POST"})
     *
     * @return JsonResponse
     */
    public function validatePinPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/pin/validate", methods={"POST"})
     * @ParamValidator({"param"="pin", "constraints"={{"Regex"="/\d{6}/"}}})
     * @OAuth2()
     *
     * @OA\Post(
     *   path="/me/pin/validate",
     *   summary="입력한 결제 비밀번호 검증",
     *   tags={"private-api"},
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"pin"},
     *       @OA\Property(property="pin", type="string", description="결제 비밀번호", example="123456"),
     *       @OA\Property(
     *         property="reservation_id",
     *         type="string",
     *         description="RIDI Pay 결제 예약 ID, [POST] /payments/reserve API 참고",
     *         example="880E8200-A29B-24B2-8716-42B65544A000"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="validation_token",
     *         type="string",
     *         description="결제 인증 후 발급된 토큰",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidParameter"),
     *         @OA\Schema(ref="#/components/schemas/PinUnmatched")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidAccessToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/PinEntryBlocked")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundUser")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validatePin(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            UserAppService::validatePin($this->getUidx(), $body->pin);

            if (isset($body->reservation_id)) {
                $validation_token = TransactionAppService::generateValidationToken($body->reservation_id);
            }
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (UnmatchedPinException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PIN_UNMATCHED,
                $e->getMessage()
            );
        } catch (PasswordEntryBlockedException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PIN_ENTRY_BLOCKED,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        $data = [];
        if (isset($validation_token)) {
            $data['validation_token'] = $validation_token;
        }

        return self::createSuccessResponse($data);
    }

    /**
     * @Route("/me/password/validate", methods={"OPTIONS"})
     * @Cors(methods={"POST"})
     *
     * @return JsonResponse
     */
    public function validatePasswordPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/password/validate", methods={"POST"})
     * @ParamValidator({"param"="password", "constraints"={"NotBlank", {"Type"="string"}}})
     * @OAuth2()
     *
     * @OA\Post(
     *   path="/me/password/validate",
     *   summary="입력한 RIDIBOOKS 계정 비밀번호 검증",
     *   tags={"private-api"},
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"password"},
     *       @OA\Property(property="password", type="string", description="RIDIBOOKS 계정 비밀번호", example="abcde@123456"),
     *       @OA\Property(
     *         property="reservation_id",
     *         type="string",
     *         description="RIDI Pay 결제 예약 ID, [POST] /payments/reserve API 참고",
     *         example="880E8200-A29B-24B2-8716-42B65544A000"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="validation_token",
     *         type="string",
     *         description="결제 인증 후 발급된 토큰",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidParameter"),
     *         @OA\Schema(ref="#/components/schemas/PasswordUnmatched")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidAccessToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/PasswordEntryBlocked")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundUser")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validatePassword(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            UserAppService::validatePassword($this->getUidx(), $this->getUid(), $body->password);

            if (isset($body->reservation_id)) {
                $validation_token = TransactionAppService::generateValidationToken($body->reservation_id);
            }
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (UnmatchedPasswordException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PASSWORD_UNMATCHED,
                $e->getMessage()
            );
        } catch (PasswordEntryBlockedException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PASSWORD_ENTRY_BLOCKED,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        $data = [];
        if (isset($validation_token)) {
            $data['validation_token'] = $validation_token;
        }

        return self::createSuccessResponse($data);
    }

    /**
     * @Route("/me/onetouch", methods={"OPTIONS"})
     * @Cors(methods={"PUT"})
     *
     * @return JsonResponse
     */
    public function updateOnetouchPayPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/onetouch", methods={"PUT"})
     * @ParamValidator({"param"="enable_onetouch_pay", "constraints"={{"Type"="bool"}}})
     * @OAuth2()
     *
     * @OA\Put(
     *   path="/me/onetouch",
     *   summary="원터치 결제 이용 여부 변경",
     *   tags={"private-api"},
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"enable_onetouch_pay"},
     *       @OA\Property(property="enable_onetouch_pay", type="boolean", description="원터치 결제 이용 여부", example=true)
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent()
     *   ),
     *   @OA\Response(
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(ref="#/components/schemas/InvalidParameter")
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidAccessToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/LeavedUser"),
     *         @OA\Schema(ref="#/components/schemas/OnetouchPaySettingChangeDeclined")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundUser")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateOnetouchPay(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            if ($body->enable_onetouch_pay) {
                UserAppService::enableOnetouchPay($this->getUidx());
            } else {
                UserAppService::disableOnetouchPay($this->getUidx());
            }
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (OnetouchPaySettingChangeDeclinedException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::ONETOUCH_PAY_SETTING_CHANGE_DECLINED,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse();
    }
}
