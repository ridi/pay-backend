<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use Ridibooks\OAuth2\Symfony\Annotation\OAuth2;
use RidiPay\Controller\Logger\ControllerAccessLogger;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Cors\Annotation\Cors;
use RidiPay\Library\Jwt\Annotation\JwtAuth;
use RidiPay\Library\SentryHelper;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Library\ValidationTokenManager;
use RidiPay\Transaction\Application\Dto\SubscriptionDto;
use RidiPay\Transaction\Application\Service\SubscriptionAppService;
use RidiPay\User\Application\Service\CardAppService;
use RidiPay\User\Domain\Entity\CardEntity;
use RidiPay\User\Domain\Entity\PaymentMethodEntity;
use RidiPay\User\Domain\Exception\PaymentMethodChangeDeclinedException;
use RidiPay\User\Domain\Exception\PinEntryBlockedException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
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
     * @Route("/users/{u_idx}", methods={"DELETE"}, requirements={"u_idx"="^\d+$"})
     * @JwtAuth(isses={"store"})
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
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidJwt")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/LeavedUser")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotFoundUser")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InternalServerError")
     *       }
     *     )
     *   )
     * )
     *
     * @param Request $request
     * @param int $u_idx
     * @return JsonResponse
     */
    public function deleteUser(Request $request, int $u_idx): JsonResponse
    {
        if ($request->getContentType() !== self::REQUEST_CONTENT_TYPE) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INVALID_CONTENT_TYPE
            );
        }

        ControllerAccessLogger::logRequest($request);

        try {
            UserAppService::deleteUser($u_idx);

            $response = self::createSuccessResponse();
        } catch (LeavedUserException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }

    /**
     * @Route("/users/{u_idx}/payment-methods", methods={"GET"}, requirements={"u_idx"="^\d+$"})
     * @JwtAuth(isses={"store", "ridiselect"})
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
     *     @OA\JsonContent(
     *       type="object",
     *       required={"cards"},
     *       @OA\Property(
     *         property="cards",
     *         type="array",
     *         @OA\Items(ref="#/components/schemas/CardInformation")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidJwt")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InternalServerError")
     *       }
     *     )
     *   )
     * )
     *
     * @param Request $request
     * @param int $u_idx
     * @return JsonResponse
     */
    public function getPaymentMethods(Request $request, int $u_idx): JsonResponse
    {
        ControllerAccessLogger::logRequest($request);

        try {
            $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
            $cards = array_filter(
                $payment_methods,
                function (PaymentMethodEntity $payment_method) {
                    return $payment_method instanceof CardEntity;
                }
            );

            $response = self::createSuccessResponse(
                [
                    'cards' => array_map(
                        function (CardEntity $card) {
                            return self::buildCardInformation($card);
                        },
                        $cards
                    )
                ]
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
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
     * @Cors(methods={"GET"})
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
     *       required={"user_id", "payment_methods", "has_pin"},
     *       @OA\Property(
     *         property="user_id",
     *         type="string",
     *         description="RIDIBOOKS Username",
     *         example="johndoe"
     *       ),
     *       @OA\Property(
     *         property="payment_methods",
     *         type="object",
     *         required={"cards"},
     *         @OA\Property(
     *           property="cards",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/CardInformation")
     *         )
     *       ),
     *       @OA\Property(
     *         property="has_pin",
     *         type="boolean",
     *         description="결제 비밀번호 등록 여부",
     *         example=true
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
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/LeavedUser")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotFoundUser")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InternalServerError")
     *       }
     *     )
     *   )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getMyInformation(Request $request): JsonResponse
    {
        $context = ['u_idx' => $this->getUidx()];
        ControllerAccessLogger::logRequest($request, $context);

        try {
            $user = UserAppService::getUser($this->getUidx());
            $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($this->getUidx());
            $cards = array_filter(
                $payment_methods,
                function (PaymentMethodEntity $payment_method) {
                    return $payment_method instanceof CardEntity;
                }
            );

            $response = self::createSuccessResponse(
                [
                    'user_id' => $this->getUid(),
                    'payment_methods' => [
                        'cards' => array_map(
                            function (CardEntity $card) {
                                return self::buildCardInformation($card);
                            },
                            $cards
                        )
                    ],
                    'has_pin' => $user->hasPin()
                ]
            );
        } catch (LeavedUserException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            $response = self::createSuccessResponse(
                [
                    'code' => UserErrorCodeConstant::NOT_FOUND_USER,
                    'message' => $e->getMessage()
                ]
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response, $context);

        return $response;
    }

    /**
     * @Route("/me/pin", methods={"OPTIONS"})
     * @Cors(methods={"POST", "PUT"})
     *
     * @return JsonResponse
     */
    public function createAndUpdatePinPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/pin", methods={"POST"})
     * @ParamValidator(
     *   rules={
     *     {"param"="pin", "constraints"={{"Regex"="/^\d{6}$/"}}},
     *     {"param"="validation_token", "constraints"={"Uuid"}}
     *   }
     * )
     * @OAuth2()
     * @Cors(methods={"POST"})
     *
     * @OA\Post(
     *   path="/me/pin",
     *   summary="결제 비밀번호 등록",
     *   tags={"private-api"},
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"pin", "validation_token"},
     *       @OA\Property(property="pin", type="string", description="결제 비밀번호", example="123456"),
     *       @OA\Property(
     *         property="validation_token",
     *         type="string",
     *         description="카드 등록, 결제 비밀번호 등록까지 필요한 인증 토큰",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"payment_method_id"},
     *       @OA\Property(
     *         property="payment_method_id",
     *         type="string",
     *         description="RIDI Pay 결제 수단 ID",
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
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/PaymentMethodChangeDeclined")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InternalServerError")
     *       }
     *     )
     *   )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPin(Request $request): JsonResponse
    {
        if ($request->getContentType() !== self::REQUEST_CONTENT_TYPE) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INVALID_CONTENT_TYPE
            );
        }

        $context = ['u_idx' => $this->getUidx()];
        ControllerAccessLogger::logRequest($request, $context);

        try {
            $body = json_decode($request->getContent());
            $card_registration_key = CardAppService::getCardRegistrationKey($this->getUidx());
            $validation_token = ValidationTokenManager::get($card_registration_key);
            if ($validation_token !== $body->validation_token) {
                $response = self::createErrorResponse(
                    CommonErrorCodeConstant::class,
                    CommonErrorCodeConstant::INVALID_VALIDATION_TOKEN
                );
                ControllerAccessLogger::logResponse($request, $response, $context);

                return $response;
            }

            UserAppService::createPin($this->getUidx(), $body->pin);

            if (empty(PaymentMethodAppService::getAvailablePaymentMethods($this->getUidx()))) {
                $card = CardAppService::finishCardRegistration($this->getOAuth2User());
            } else {
                $card = CardAppService::changeCard($this->getOAuth2User());
            }

            ValidationTokenManager::invalidate($card_registration_key);

            $response = self::createSuccessResponse(['payment_method_id' => $card->getUuid()->toString()]);
        } catch (PaymentMethodChangeDeclinedException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PAYMENT_METHOD_CHANGE_DECLINED,
                $e->getMessage()
            );
        } catch (WrongFormattedPinException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::WRONG_FORMATTED_PIN,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response, $context);

        return $response;
    }

    /**
     * @Route("/me/pin", methods={"PUT"})
     * @ParamValidator(
     *   rules={
     *     {"param"="pin", "constraints"={{"Regex"="/^\d{6}$/"}}},
     *     {"param"="validation_token", "constraints"={"Uuid"}}
     *   }
     * )
     * @OAuth2()
     * @Cors(methods={"PUT"})
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
     *         @OA\Schema(ref="#/components/schemas/InvalidValidationToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired"),
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/LeavedUser")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotFoundUser")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InternalServerError")
     *       }
     *     )
     *   )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePin(Request $request)
    {
        if ($request->getContentType() !== self::REQUEST_CONTENT_TYPE) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INVALID_CONTENT_TYPE
            );
        }

        $context = ['u_idx' => $this->getUidx()];
        ControllerAccessLogger::logRequest($request, $context);

        try {
            $body = json_decode($request->getContent());
            $user_key = UserAppService::getUserKey($this->getUidx());
            $validation_token = ValidationTokenManager::get($user_key);
            if ($validation_token !== $body->validation_token) {
                $response = self::createErrorResponse(
                    CommonErrorCodeConstant::class,
                    CommonErrorCodeConstant::INVALID_VALIDATION_TOKEN
                );
                ControllerAccessLogger::logResponse($request, $response, $context);

                return $response;
            }

            UserAppService::updatePin($this->getOAuth2User(), $body->pin);
            ValidationTokenManager::invalidate($user_key);

            $response = self::createSuccessResponse();
        } catch (LeavedUserException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (UnchangedPinException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNCHANGED_PIN,
                $e->getMessage()
            );
        } catch (WrongFormattedPinException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::WRONG_FORMATTED_PIN,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response, $context);

        return $response;
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
     * @ParamValidator(
     *   rules={
     *     {"param"="pin", "constraints"={{"Regex"="/^\d{6}$/"}}}
     *   }
     * )
     * @OAuth2()
     * @Cors(methods={"POST"})
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
     *         description="결제 비밀번호 확인 후 발급된 토큰",
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
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/PinEntryBlocked")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotFoundUser")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InternalServerError")
     *       }
     *     )
     *   )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validatePin(Request $request)
    {
        if ($request->getContentType() !== self::REQUEST_CONTENT_TYPE) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INVALID_CONTENT_TYPE
            );
        }

        $context = ['u_idx' => $this->getUidx()];
        ControllerAccessLogger::logRequest($request, $context);

        try {
            $body = json_decode($request->getContent());
            UserAppService::validatePin($this->getUidx(), $body->pin);

            $validation_token = UserAppService::generateValidationToken($this->getUidx());

            $response = self::createSuccessResponse(['validation_token' => $validation_token]);
        } catch (LeavedUserException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (UnmatchedPinException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PIN_UNMATCHED,
                $e->getMessage()
            );
        } catch (PinEntryBlockedException $e) {
            $response = self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PIN_ENTRY_BLOCKED,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response, $context);

        return $response;
    }

    /**
     * @OA\Schema(
     *   schema="CardInformation",
     *   type="object",
     *   required={
     *     "payment_method_id",
     *     "iin",
     *     "issuer_name",
     *     "color",
     *     "logo_image_url",
     *     "subscriptions"
     *   },
     *   @OA\Property(property="payment_method_id", type="string", example="550E8400-E29B-41D4-A716-446655440000"),
     *   @OA\Property(property="iin", type="string", example="449914"),
     *   @OA\Property(property="issuer_name", type="string", example="신한카드"),
     *   @OA\Property(property="color", type="string", example="#FFFFFF"),
     *   @OA\Property(property="logo_image_url", type="string"),
     *   @OA\Property(
     *     property="subscriptions",
     *     type="array",
     *     @OA\Items(type="string")
     *   )
     * )
     *
     * @param CardEntity $card
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private function buildCardInformation(CardEntity $card): array
    {
        return [
            'payment_method_id' => $card->getUuid()->toString(),
            'iin' => $card->getIin(),
            'issuer_name' => $card->getCardIssuer()->getName(),
            'color' => $card->getCardIssuer()->getColor(),
            'logo_image_url' => $card->getCardIssuer()->getLogoImageUrl(),
            'subscriptions' => array_unique(array_map(
                function (SubscriptionDto $subscription) {
                    return $subscription->product_name;
                },
                SubscriptionAppService::getSubscriptionByPaymentMethodId($card->getId())
            )),
        ];
    }
}
