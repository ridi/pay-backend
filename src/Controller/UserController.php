<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use Ridibooks\OAuth2\Symfony\Annotation\OAuth2;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Cors\Annotation\Cors;
use RidiPay\Library\Jwt\Annotation\JwtAuth;
use RidiPay\Library\SentryHelper;
use RidiPay\Library\TemplateRenderer;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use RidiPay\User\Application\Service\CardAppService;
use RidiPay\User\Application\Service\EmailSender;
use RidiPay\User\Domain\Exception\PinEntryBlockedException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\OnetouchPaySettingChangeDeclinedException;
use RidiPay\User\Domain\Exception\UnauthorizedCardRegistrationException;
use RidiPay\User\Domain\Exception\UnauthorizedPinChangeException;
use RidiPay\User\Domain\Exception\UnchangedPinException;
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
     *     @OA\JsonContent(type="object")
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
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

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
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

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
     *     @OA\JsonContent(
     *       type="object",
     *       required={"user_id", "payment_methods", "has_pin", "is_using_onetouch_pay"},
     *       @OA\Property(
     *         property="user_id",
     *         type="string",
     *         description="RIDIBOOKS Username",
     *         example="johndoe"
     *       ),
     *       @OA\Property(
     *         property="payment_methods",
     *         ref="#/components/schemas/AvailablePaymentMethodsDto"
     *       ),
     *       @OA\Property(
     *         property="has_pin",
     *         type="boolean",
     *         description="결제 비밀번호 등록 여부",
     *         example=true
     *       ),
     *       @OA\Property(
     *         property="is_using_onetouch_pay",
     *         type="boolean",
     *         description="원터치 결제 이용 여부",
     *         example=true,
     *         nullable=true
     *       )
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
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse([
            'user_id' => $this->getUid(),
            'payment_methods' => $user_information->payment_methods,
            'has_pin' => $user_information->has_pin,
            'is_using_onetouch_pay' => $user_information->is_using_onetouch_pay
        ]);
    }

    /**
     * @Route("/me/pin", methods={"OPTIONS"})
     * @Cors(methods={"POST, PUT"})
     *
     * @return JsonResponse
     */
    public function createAndUpdatePinPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/pin", methods={"POST"})
     * @ParamValidator({"param"="pin", "constraints"={{"Regex"="/\d{6}/"}}})
     * @OAuth2()
     *
     * @OA\Post(
     *   path="/me/pin",
     *   summary="결제 비밀번호 등록",
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
     *     @OA\JsonContent(type="object")
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
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPin(Request $request): JsonResponse
    {
        try {
            $body = json_decode($request->getContent());
            UserAppService::createPin($this->getUidx(), $body->pin);
        } catch (WrongFormattedPinException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::WRONG_FORMATTED_PIN,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/pin", methods={"PUT"})
     * @ParamValidator(
     *   {"param"="pin", "constraints"={{"Regex"="/\d{6}/"}}},
     *   {"param"="validation_token", "constraints"={"Uuid"}}
     * )
     * @OAuth2()
     *
     * @OA\Put(
     *   path="/me/pin",
     *   summary="결제 비밀번호 변경",
     *   tags={"private-api"},
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"pin", "validation_token"},
     *       @OA\Property(property="pin", type="string", description="결제 비밀번호", example="123456"),
     *       @OA\Property(
     *         property="validation_token",
     *         type="string",
     *         description="결제 비밀번호 검증 시 발급된 토큰",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(type="object")
     *   ),
     *   @OA\Response(
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidParameter"),
     *         @OA\Schema(ref="#/components/schemas/WrongFormattedPin"),
     *         @OA\Schema(ref="#/components/schemas/UnchangedPin")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidAccessToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired"),
     *         @OA\Schema(ref="#/components/schemas/UnauthorizedPinChange")
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
            UserAppService::updatePin($this->getUidx(), $body->pin, $body->validation_token);

            $data = ['u_id' => $this->getUid()];
            $email_body = (new TemplateRenderer())->render('pin-change-alert.twig', $data);
            EmailSender::send(
                $this->getEmail(),
                "[RIDI Pay] {$this->getUid()}님, 결제 비밀번호 변경 안내드립니다.",
                $email_body
            );
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
        } catch (UnauthorizedPinChangeException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNAUTHORIZED_PIN_CHANGE,
                $e->getMessage()
            );
        } catch (UnchangedPinException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNCHANGED_PIN,
                $e->getMessage()
            );
        } catch (WrongFormattedPinException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::WRONG_FORMATTED_PIN,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

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
     *         description="결제 비밀번호 인증 후 발급된 토큰",
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
            } else {
                $validation_token = UserAppService::generateValidationToken($this->getUidx());
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
        } catch (PinEntryBlockedException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PIN_ENTRY_BLOCKED,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse(['validation_token' => $validation_token]);
    }

    /**
     * @Route("/me/onetouch", methods={"OPTIONS"})
     * @Cors(methods={"POST, PUT"})
     *
     * @return JsonResponse
     */
    public function setAndChangeOnetouchPayPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/onetouch", methods={"POST"})
     * @ParamValidator({"param"="enable_onetouch_pay", "constraints"={{"Type"="bool"}}})
     * @OAuth2()
     *
     * @OA\Post(
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
     *     @OA\JsonContent(type="object")
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
     *         @OA\Schema(ref="#/components/schemas/LoginRequired"),
     *         @OA\Schema(ref="#/components/schemas/UnauthorizedCardRegistration")
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
    public function setOnetouchPay(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            UserAppService::setOnetouchPay($this->getUidx(), $body->enable_onetouch_pay);

            $card = CardAppService::finishCardRegistration($this->getUidx());

            $data = [
                'card_issuer_name' => $card->issuer_name,
                'iin' => $card->iin
            ];
            $email_body = (new TemplateRenderer())->render('card-registration-alert.twig', $data);
            EmailSender::send(
                $this->getEmail(),
                "[RIDI Pay] {$this->getUid()}님, 카드 등록 안내드립니다.",
                $email_body
            );
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
        } catch (UnauthorizedCardRegistrationException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNAUTHORIZED_CARD_REGISTRATION,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

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
     *       @OA\Property(property="enable_onetouch_pay", type="boolean", description="원터치 결제 이용 여부", example=true),
     *       @OA\Property(
     *         property="validation_token",
     *         type="string",
     *         description="결제 비밀번호 인증 후 발급된 토큰",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(type="object")
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
    public function changeOnetouchPay(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            if ($body->enable_onetouch_pay) {
                if (!isset($body->validation_token)) {
                    return self::createErrorResponse(
                        CommonErrorCodeConstant::class,
                        CommonErrorCodeConstant::INVALID_PARAMETER,
                        "validation_token: Parameter doesn't exist."
                    );
                }

                UserAppService::enableOnetouchPay($this->getUidx(), $body->validation_token);

                $data = [
                    'u_id' => $this->getUid(),
                    'enable_onetouch_pay' => $body->enable_onetouch_pay
                ];
                $email_body = (new TemplateRenderer())->render('onetouch-pay-change-alert.twig', $data);
                EmailSender::send(
                    $this->getEmail(),
                    "[RIDI Pay] {$this->getUid()}님, 원터치 결제 설정 변경 안내드립니다.",
                    $email_body
                );
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
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse();
    }
}
