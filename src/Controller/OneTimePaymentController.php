<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use Ridibooks\OAuth2\Symfony\Annotation\OAuth2;
use RidiPay\Controller\Logger\ControllerAccessLogger;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\PartnerErrorCodeConstant;
use RidiPay\Controller\Response\PgErrorCodeConstant;
use RidiPay\Controller\Response\TransactionErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Cors\Annotation\Cors;
use RidiPay\Library\DuplicatedRequestException;
use RidiPay\Library\Pg\Kcp\UnderMinimumPaymentAmountException;
use RidiPay\Library\SentryHelper;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Library\Validation\ApiSecretValidationException;
use RidiPay\Library\Validation\ApiSecretValidator;
use RidiPay\Library\ValidationTokenManager;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledTransactionException;
use RidiPay\Transaction\Domain\Exception\NotFoundTransactionException;
use RidiPay\Transaction\Domain\Exception\NotReservedTransactionException;
use RidiPay\User\Application\Service\UserAppService;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class OneTimePaymentController extends BaseController
{
    /**
     * @Route("/payments/reserve", methods={"POST"})
     * @ParamValidator(
     *   rules={
     *     {"param"="payment_method_id", "constraints"={"Uuid"}},
     *     {"param"="partner_transaction_id", "constraints"={"NotBlank", {"Type"="string"}}},
     *     {"param"="product_name", "constraints"={"NotBlank", {"Type"="string"}}},
     *     {"param"="amount", "constraints"={{"Regex"="/^\d+$/"}}},
     *     {"param"="return_url", "constraints"={"Url"}}
     *   }
     * )
     *
     * @OA\Post(
     *   path="/payments/reserve",
     *   summary="결제 예약",
     *   tags={"public-api"},
     *   @OA\Parameter(ref="#/components/parameters/Api-Key"),
     *   @OA\Parameter(ref="#/components/parameters/Secret-Key"),
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"payment_method_id", "partner_transaction_id", "product_name", "amount", "return_url"},
     *       @OA\Property(
     *         property="payment_method_id",
     *         type="string",
     *         description="RIDI Pay 결제 수단 ID",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       ),
     *       @OA\Property(property="partner_transaction_id", type="string", description="가맹점 주문 번호"),
     *       @OA\Property(property="product_name", type="string", description="결제 상품", example="리디북스 전자책"),
     *       @OA\Property(property="amount", type="integer", description="결제 금액", example="10000"),
     *       @OA\Property(
     *         property="return_url",
     *         type="string",
     *         description="RIDI Pay 결제 비밀번호 확인 성공/실패 후, Redirect 되는 가맹점 URL",
     *         example="https://ridibooks.com/payment/callback/ridi-pay"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"reservation_id"},
     *       @OA\Property(property="reservation_id", type="string", example="880E8200-A29B-24B2-8716-42B65544A000")
     *     )
     *   ),
     *   @OA\Response(
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidParameter"),
     *         @OA\Schema(ref="#/components/schemas/UnderMinimumPaymentAmount")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/UnauthorizedPartner")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/DeletedPaymentMethod")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/UnregisteredPaymentMethod")
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
    public function reservePayment(Request $request): JsonResponse
    {
        if ($request->getContentType() !== BaseController::REQUEST_CONTENT_TYPE) {
            return BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INVALID_CONTENT_TYPE
            );
        }

        ControllerAccessLogger::logRequest($request);

        try {
            $partner_api_secret = ApiSecretValidator::validate($request);

            $body = json_decode($request->getContent());
            $reservation_id = TransactionAppService::reserveTransaction(
                $partner_api_secret,
                $body->payment_method_id,
                $body->partner_transaction_id,
                $body->product_name,
                intval($body->amount),
                $body->return_url
            );

            $response = BaseController::createSuccessResponse(['reservation_id' => $reservation_id]);
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            $response = BaseController::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (UnregisteredPaymentMethodException $e) {
            $response = BaseController::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (DeletedPaymentMethodException $e) {
            $response = BaseController::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::DELETED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (UnderMinimumPaymentAmountException $e) {
            $response = BaseController::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::UNDER_MINIMUM_PAYMENT_AMOUNT,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }

    /**
     * @Route(
     *   "/payments/{reservation_id}",
     *   methods={"OPTIONS"},
     *   requirements={
     *     "reservation_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
     *   }
     * )
     * @Cors(methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getReservationPreflight(): JsonResponse
    {
        return BaseController::createSuccessResponse();
    }

    /**
     * @Route(
     *   "/payments/{reservation_id}",
     *   methods={"GET"},
     *   requirements={
     *     "reservation_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
     *   }
     * )
     * @OAuth2()
     * @Cors(methods={"GET"})
     *
     * @OA\Get(
     *   path="/payments/{reservation_id}",
     *   summary="결제 예약 정보 조회",
     *   tags={"private-api"},
     *   @OA\Parameter(
     *     name="reservation_id",
     *     in="path",
     *     required=true,
     *     description="RIDI Pay 결제 예약 ID, [POST] /payments/reserve API 참고",
     *     @OA\Schema(type="string")
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
     *         @OA\Schema(ref="#/components/schemas/InvalidAccessToken"),
     *         @OA\Schema(ref="#/components/schemas/InvalidValidationToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/DeletedPaymentMethod")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotReservedTransaction")
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
     * @param string $reservation_id
     * @return JsonResponse
     */
    public function getReservation(Request $request, string $reservation_id): JsonResponse
    {
        $context = ['u_idx' => $this->getUidx()];
        ControllerAccessLogger::logRequest($request, $context);

        try {
            TransactionAppService::getReservedTransaction($reservation_id, $this->getUidx());

            $response = self::createSuccessResponse();
        } catch (DeletedPaymentMethodException $e) {
            $response = BaseController::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::DELETED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (NotReservedTransactionException $e) {
            $response = BaseController::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_RESERVED_TRANSACTION,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }

    /**
     * @Route(
     *   "/payments/{reservation_id}",
     *   methods={"OPTIONS"},
     *   requirements={
     *     "reservation_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
     *   }
     * )
     * @Cors(methods={"POST"})
     *
     * @return JsonResponse
     */
    public function createPaymentPreflight(): JsonResponse
    {
        return BaseController::createSuccessResponse();
    }

    /**
     * @Route(
     *   "/payments/{reservation_id}",
     *   methods={"POST"},
     *   requirements={
     *     "reservation_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
     *   }
     * )
     * @ParamValidator(
     *   rules={
     *     {"param"="validation_token", "constraints"={"Uuid"}}
     *   }
     * )
     * @OAuth2()
     * @Cors(methods={"POST"})
     *
     * @OA\Post(
     *   path="/payments/{reservation_id}",
     *   summary="결제 비밀번호 확인 성공 후, 결제 생성",
     *   tags={"private-api"},
     *   @OA\Parameter(
     *     name="reservation_id",
     *     in="path",
     *     required=true,
     *     description="RIDI Pay 결제 예약 ID, [POST] /payments/reserve API 참고",
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"validation_token"},
     *       @OA\Property(
     *         property="validation_token",
     *         type="string",
     *         description="결제 비밀번호 확인 후 발급된 토큰",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       required={"return_url"},
     *       @OA\Property(
     *         property="return_url",
     *         type="string",
     *         example="https://ridibooks.com/payment/callback/ridi-pay?transaction_id=550E8400-E29B-41D4-A716-446655440000"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidParameter")
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
     *         @OA\Schema(ref="#/components/schemas/DeletedPaymentMethod")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotReservedTransaction")
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
     * @param string $reservation_id
     * @return JsonResponse
     */
    public function createPayment(Request $request, string $reservation_id): JsonResponse
    {
        if ($request->getContentType() !== BaseController::REQUEST_CONTENT_TYPE) {
            return BaseController::createErrorResponse(
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
                $response = BaseController::createErrorResponse(
                    CommonErrorCodeConstant::class,
                    CommonErrorCodeConstant::INVALID_VALIDATION_TOKEN
                );
                ControllerAccessLogger::logResponse($request, $response, $context);

                return $response;
            }

            $result = TransactionAppService::createTransaction($this->getUidx(), $reservation_id);
            ValidationTokenManager::invalidate($user_key);

            $response = BaseController::createSuccessResponse([
                'return_url' => $result->return_url . '?' . http_build_query(['transaction_id' => $result->transaction_id])
            ]);
        } catch (DeletedPaymentMethodException $e) {
            $response = BaseController::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::DELETED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (NotReservedTransactionException $e) {
            $response = BaseController::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_RESERVED_TRANSACTION,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response, $context);

        return $response;
    }

    /**
     * @Route(
     *   "/payments/{transaction_id}/approve",
     *   methods={"POST"},
     *   requirements={
     *     "transaction_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
     *   }
     * )
     * @ParamValidator(
     *   rules={
     *     {"param"="buyer_id", "constraints"={"NotBlank", {"Type"="string"}}},
     *     {"param"="buyer_name", "constraints"={"NotBlank", {"Type"="string"}}},
     *     {"param"="buyer_email", "constraints"={"Email"}}
     *   }
     * )
     *
     * @OA\Post(
     *   path="/payments/{transaction_id}/approve",
     *   summary="결제 승인",
     *   tags={"public-api"},
     *   @OA\Parameter(ref="#/components/parameters/Api-Key"),
     *   @OA\Parameter(ref="#/components/parameters/Secret-Key"),
     *   @OA\Parameter(
     *     name="transaction_id",
     *     in="path",
     *     required=true,
     *     description="RIDI Pay 주문 번호",
     *     example="550E8400-E29B-41D4-A716-446655440000",
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"buyer_id", "buyer_name", "buyer_email"},
     *       @OA\Property(property="buyer_id", type="string", description="구매자 ID(가맹점)"),
     *       @OA\Property(property="buyer_name", type="string", description="구매자 이름"),
     *       @OA\Property(property="buyer_email", type="string", description="구매자 Email")
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       required={
     *         "transaction_id",
     *         "partner_transaction_id",
     *         "product_name",
     *         "amount",
     *         "reserved_at",
     *         "approved_at"
     *       },
     *       @OA\Property(
     *         property="transaction_id",
     *         type="string",
     *         description="RIDI Pay 주문 번호",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       ),
     *       @OA\Property(property="partner_transaction_id", type="string", description="가맹점 주문 번호"),
     *       @OA\Property(property="product_name", type="string", description="결제 상품", example="리디북스 전자책"),
     *       @OA\Property(property="amount", type="integer", description="결제 금액", example="10000"),
     *       @OA\Property(
     *         property="reserved_at",
     *         type="string",
     *         description="결제 예약 일시(ISO 8601 Format)",
     *         example="2018-06-07T01:59:30+09:00"
     *       ),
     *       @OA\Property(
     *         property="approved_at",
     *         type="string",
     *         description="결제 승인 일시(ISO 8601 Format)",
     *         example="2018-06-07T01:59:59+09:00"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/UnderMinimumPaymentAmount")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/UnauthorizedPartner")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/AlreadyCancelledTransaction"),
     *         @OA\Schema(ref="#/components/schemas/DuplicatedRequest"),
     *         @OA\Schema(ref="#/components/schemas/DeletedPaymentMethod")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotFoundTransaction")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/TransactionApprovalFailed"),
     *         @OA\Schema(ref="#/components/schemas/InternalServerError")
     *       }
     *     )
     *   )
     * )
     *
     * @param Request $request
     * @param string $transaction_id
     * @return JsonResponse
     */
    public function approvePayment(Request $request, string $transaction_id): JsonResponse
    {
        if ($request->getContentType() !== BaseController::REQUEST_CONTENT_TYPE) {
            return BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INVALID_CONTENT_TYPE
            );
        }

        ControllerAccessLogger::logRequest($request);

        try {
            $partner_api_secret = ApiSecretValidator::validate($request);

            $body = json_decode($request->getContent());
            $result = TransactionAppService::approveTransaction(
                $partner_api_secret,
                $transaction_id,
                $body->buyer_id,
                $body->buyer_name,
                $body->buyer_email
            );

            $response = BaseController::createSuccessResponse([
                'transaction_id' => $result->transaction_id,
                'partner_transaction_id' => $result->partner_transaction_id,
                'product_name' => $result->product_name,
                'amount' => $result->amount,
                'reserved_at' => $result->reserved_at->format(DATE_ATOM),
                'approved_at' => $result->approved_at->format(DATE_ATOM)
            ]);
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            $response = BaseController::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotFoundTransactionException $e) {
            $response = BaseController::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_FOUND_TRANSACTION,
                $e->getMessage()
            );
        } catch (AlreadyCancelledTransactionException $e) {
            $response = BaseController::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::ALREADY_CANCELLED_TRANSACTION,
                $e->getMessage()
            );
        } catch (DuplicatedRequestException $e) {
            $response = BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::DUPLICATED_REQUEST,
                $e->getMessage()
            );
        } catch (DeletedPaymentMethodException $e) {
            $response = BaseController::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::DELETED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (UnderMinimumPaymentAmountException $e) {
            $response = BaseController::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::UNDER_MINIMUM_PAYMENT_AMOUNT,
                $e->getMessage()
            );
        } catch (TransactionApprovalException $e) {
            $response = BaseController::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::TRANSACTION_APPROVAL_FAILED,
                $e->getMessage(),
                ['pg_message' => $e->getPgMessage()]
            );
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            $response = BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }
}
