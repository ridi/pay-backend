<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use Ridibooks\OAuth2\Symfony\Annotation\OAuth2;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\PartnerErrorCodeConstant;
use RidiPay\Controller\Response\PgErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Cors\Annotation\Cors;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Library\Validation\ApiSecretValidationException;
use RidiPay\Library\Validation\ApiSecretValidator;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\TransactionCancellationException;
use RidiPay\Transaction\Application\Service\SubscriptionAppService;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use RidiPay\Transaction\Domain\Exception\NotFoundTransactionException;
use RidiPay\Transaction\Domain\Exception\NotReservedTransactionException;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Controller\Response\TransactionErrorCodeConstant;
use RidiPay\Transaction\Domain\Exception\UnvalidatedTransactionException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends BaseController
{
    /**
     * @Route("/payments/reserve", methods={"POST"})
     * @ParamValidator(
     *   {"param"="payment_method_id", "constraints"={"Uuid"}},
     *   {"param"="partner_transaction_id", "constraints"={"NotBlank", {"Type"="string"}}},
     *   {"param"="product_name", "constraints"={"NotBlank", {"Type"="string"}}},
     *   {"param"="amount", "constraints"={{"Regex"="/\d+/"}}},
     *   {"param"="return_url", "constraints"={"Url"}}
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
     *         description="RIDI Pay 결제 인증 성공/실패 후, Redirect 되는 가맹점 URL",
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
     *     @OA\JsonContent(ref="#/components/schemas/InvalidParameter")
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/UnauthorizedPartner")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/UnregisteredPaymentMethod")
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
    public function reservePayment(Request $request): JsonResponse
    {
        try {
            ApiSecretValidator::validate($request);

            $body = json_decode($request->getContent());
            $reservation_id = TransactionAppService::reserveTransaction(
                ApiSecretValidator::getApiKey($request),
                ApiSecretValidator::getSecretKey($request),
                $body->payment_method_id,
                $body->partner_transaction_id,
                $body->product_name,
                intval($body->amount),
                $body->return_url
            );
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            return self::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (UnregisteredPaymentMethodException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse(['reservation_id' => $reservation_id]);
    }

    /**
     * @Route(
     *   "/payments/{reservation_id}",
     *   methods={"OPTIONS"},
     *   requirements={
     *     "reservation_id"="[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}"
     *   }
     * )
     * @Cors(methods={"GET"})
     *
     * @return JsonResponse
     */
    public function getReservationPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route(
     *   "/payments/{reservation_id}",
     *   methods={"GET"},
     *   requirements={
     *     "reservation_id"="[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}"
     *   }
     * )
     * @OAuth2()
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
     *     @OA\JsonContent(
     *       type="object",
     *       required={"is_pin_validation_required"},
     *       @OA\Property(
     *         property="is_pin_validation_required",
     *         type="boolean",
     *         description="결제 비밀번호 검증 필요 여부",
     *         example=true
     *       ),
     *       @OA\Property(
     *         property="validation_token",
     *         type="string",
     *         description="is_pin_validation_required = false인 경우, 발급된 인증 토큰",
     *         example="550E8400-E29B-41D4-A716-446655440000"
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
     *         @OA\Schema(ref="#/components/schemas/LeavedUser"),
     *         @OA\Schema(ref="#/components/schemas/UnvalidatedTransaction")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotFoundUser"),
     *         @OA\Schema(ref="#/components/schemas/NotReservedTransaction")
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
     * @param string $reservation_id
     * @return JsonResponse
     */
    public function getReservation(string $reservation_id): JsonResponse
    {
        try {
            $is_pin_validation_required = TransactionAppService::isPinValidationRequired(
                $reservation_id,
                $this->getUidx()
            );
            if (!$is_pin_validation_required) {
                $validation_token = TransactionAppService::generateValidationToken($reservation_id);
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
        } catch (NotReservedTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_RESERVED_TRANSACTION,
                $e->getMessage()
            );
        } catch (UnvalidatedTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::UNVALIDATED_TRANSACTION,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        $data = ['is_pin_validation_required' => $is_pin_validation_required];
        if (isset($validation_token)) {
            $data['validation_token'] = $validation_token;
        }

        return self::createSuccessResponse($data);
    }

    /**
     * @Route(
     *   "/payments/{reservation_id}",
     *   methods={"OPTIONS"},
     *   requirements={
     *     "reservation_id"="[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}"
     *   }
     * )
     * @Cors(methods={"POST"})
     *
     * @return JsonResponse
     */
    public function createPaymentPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route(
     *   "/payments/{reservation_id}",
     *   methods={"POST"},
     *   requirements={
     *     "reservation_id"="[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}"
     *   }
     * )
     * @ParamValidator({"param"="validation_token", "constraints"={"Uuid"}})
     * @OAuth2()
     *
     * @OA\Post(
     *   path="/payments/{reservation_id}",
     *   summary="결제 인증 성공 후, 결제 생성",
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
     *         description="결제 인증 후 발급된 토큰",
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
     *     @OA\JsonContent(ref="#/components/schemas/UnvalidatedTransaction")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotReservedTransaction")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @param Request $request
     * @param string $reservation_id
     * @return JsonResponse
     */
    public function createPayment(Request $request, string $reservation_id): JsonResponse
    {
        try {
            $body = json_decode($request->getContent());
            $result = TransactionAppService::createTransaction(
                $this->getUidx(),
                $reservation_id,
                $body->validation_token
            );
        } catch (NotReservedTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_RESERVED_TRANSACTION,
                $e->getMessage()
            );
        } catch (UnvalidatedTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::UNVALIDATED_TRANSACTION
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse([
            'return_url' => $result->return_url . '?' . http_build_query(['transaction_id' => $result->transaction_id])
        ]);
    }

    /**
     * @Route(
     *   "/payments/{transaction_id}/approve",
     *   methods={"POST"},
     *   requirements={
     *     "transaction_id"="[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}"
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
     *       required={"validation_token"},
     *       @OA\Property(
     *         property="validation_token",
     *         type="string",
     *         description="결제 인증 후 발급된 토큰",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       )
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
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/UnauthorizedPartner"),
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundTransaction")
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
        try {
            ApiSecretValidator::validate($request);

            $result = TransactionAppService::approveTransaction(
                ApiSecretValidator::getApiKey($request),
                ApiSecretValidator::getSecretKey($request),
                $transaction_id
            );
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            return self::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotFoundTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_FOUND_TRANSACTION,
                $e->getMessage()
            );
        } catch (TransactionApprovalException $e) {
            return self::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::TRANSACTION_APPROVAL_FAILED,
                $e->getMessage(),
                ['pg_message' => $e->getPgMessage()]
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse([
            'transaction_id' => $result->transaction_id,
            'partner_transaction_id' => $result->partner_transaction_id,
            'product_name' => $result->product_name,
            'amount' => $result->amount,
            'reserved_at' => $result->reserved_at->format(DATE_ATOM),
            'approved_at' => $result->approved_at->format(DATE_ATOM)
        ]);
    }

    /**
     * @Route(
     *   "/payments/{transaction_id}/cancel",
     *   methods={"POST"},
     *   requirements={
     *     "transaction_id"="[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}"
     *   }
     * )
     *
     * @OA\Post(
     *   path="/payments/{transaction_id}/cancel",
     *   summary="결제 취소",
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
     *         "approved_at",
     *         "canceled_at",
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
     *       ),
     *       @OA\Property(
     *         property="canceled_at",
     *         type="string",
     *         description="결제 취소 일시(ISO 8601 Format)",
     *         example="2018-06-07T01:59:59+09:00"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/UnauthorizedPartner"),
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundTransaction")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/TransactionCancellationFailed"),
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
    public function cancelPayment(Request $request, string $transaction_id): JsonResponse
    {
        try {
            ApiSecretValidator::validate($request);

            $result = TransactionAppService::cancelTransaction(
                ApiSecretValidator::getApiKey($request),
                ApiSecretValidator::getSecretKey($request),
                $transaction_id
            );
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            return self::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotFoundTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_FOUND_TRANSACTION,
                $e->getMessage()
            );
        } catch (TransactionCancellationException $e) {
            return self::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::TRANSACTION_CANCELLATION_FAILED,
                $e->getMessage(),
                ['pg_message' => $e->getPgMessage()]
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse([
            'transaction_id' => $result->transaction_id,
            'partner_transaction_id' => $result->partner_transaction_id,
            'product_name' => $result->product_name,
            'amount' => $result->amount,
            'reserved_at' => $result->reserved_at->format(DATE_ATOM),
            'approved_at' => $result->approved_at->format(DATE_ATOM),
            'canceled_at' => $result->canceled_at->format(DATE_ATOM)
        ]);
    }

    /**
     * @Route(
     *   "/payments/{transaction_id}/status",
     *   methods={"GET"},
     *   requirements={
     *     "transaction_id"="[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}"
     *   }
     * )
     *
     * @OA\Get(
     *   path="/payments/{transaction_id}/status",
     *   summary="결제 상태 조회",
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
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       required={
     *         "transaction_id",
     *         "partner_transaction_id",
     *         "payment_method_id",
     *         "payment_method_type",
     *         "status",
     *         "product_name",
     *         "amount",
     *         "reserved_at"
     *       },
     *       @OA\Property(
     *         property="transaction_id",
     *         type="string",
     *         description="RIDI Pay 주문 번호",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       ),
     *       @OA\Property(property="partner_transaction_id", type="string", description="가맹점 주문 번호"),
     *       @OA\Property(
     *         property="payment_method_id",
     *         type="string",
     *         description="RIDI Pay 결제 수단 ID",
     *         example="880E8200-A29B-24B2-8716-42B65544A000"
     *       ),
     *       @OA\Property(property="payment_method_type", type="string", description="RIDI Pay 결제 수단 종류", example="CARD"),
     *       @OA\Property(property="status", type="string", enum={"RESERVED", "APPROVED", "CANCELED"}, description="결제 상태"),
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
     *       ),
     *       @OA\Property(
     *         property="canceled_at",
     *         type="string",
     *         description="결제 취소 일시(ISO 8601 Format)",
     *         example="2018-06-07T01:59:59+09:00"
     *       ),
     *       @OA\Property(
     *         property="card_receipt_url",
     *         type="string",
     *         description="신용카드 매출 전표 URL",
     *         example="https://admin8.kcp.co.kr/assist/bill.BillActionNew.do?cmd=card_bill&tno=kcp_tno&order_no=order_no&trade_mony=100"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/UnauthorizedPartner"),
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundTransaction")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @param Request $request
     * @param string $transaction_id
     * @return JsonResponse
     */
    public function getPaymentStatus(Request $request, string $transaction_id): JsonResponse
    {
        try {
            ApiSecretValidator::validate($request);

            $result = TransactionAppService::getTransactionStatus(
                ApiSecretValidator::getApiKey($request),
                ApiSecretValidator::getSecretKey($request),
                $transaction_id
            );
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            return self::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotFoundTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_FOUND_TRANSACTION,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        $data = [
            'transaction_id' => $result->transaction_id,
            'partner_transaction_id' => $result->partner_transaction_id,
            'payment_method_id' => $result->payment_method_id,
            'payment_method_type' => $result->payment_method_type,
            'status' => $result->status,
            'product_name' => $result->product_name,
            'amount' => $result->amount,
            'reserved_at' => $result->reserved_at->format(DATE_ATOM)
        ];
        if (!is_null($result->approved_at)) {
            $data['approved_at'] = $result->approved_at->format(DATE_ATOM);
        }
        if (!is_null($result->canceled_at)) {
            $data['canceled_at'] = $result->canceled_at->format(DATE_ATOM);
        }
        if (!is_null($result->card_receipt_url)) {
            $data['card_receipt_url'] = $result->card_receipt_url;
        }

        return self::createSuccessResponse($data);
    }

    /**
     * @Route("/payments/subscriptions", methods={"POST"})
     * @ParamValidator(
     *   {"param"="payment_method_id", "constraints"={"Uuid"}},
     *   {"param"="product_name", "constraints"={"NotBlank", {"Type"="string"}}},
     *   {"param"="amount", "constraints"={{"Regex"="/\d+/"}}},
     * )
     *
     * @OA\Post(
     *   path="/payments/subscriptions",
     *   summary="정기 결제 등록",
     *   tags={"public-api"},
     *   @OA\Parameter(ref="#/components/parameters/Api-Key"),
     *   @OA\Parameter(ref="#/components/parameters/Secret-Key"),
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"payment_method_id", "product_name", "amount"},
     *       @OA\Property(
     *         property="payment_method_id",
     *         type="string",
     *         description="RIDI Pay 결제 수단 ID",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       ),
     *       @OA\Property(property="product_name", type="string", description="결제 상품", example="리디북스 전자책"),
     *       @OA\Property(property="amount", type="integer", description="결제 금액", example="10000")
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       required={
     *         "subscription_id",
     *         "payment_method_id",
     *         "product_name",
     *         "amount",
     *         "subscribed_at"
     *       },
     *       @OA\Property(
     *         property="subscription_id",
     *         type="string",
     *         description="RIDI Pay 정기 결제 ID",
     *         example="880E8200-A29B-24B2-8716-42B65544A000"
     *       ),
     *       @OA\Property(
     *         property="payment_method_id",
     *         type="string",
     *         description="RIDI Pay 결제 수단 ID",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       ),
     *       @OA\Property(property="product_name", type="string", description="결제 상품", example="리디북스 전자책"),
     *       @OA\Property(property="amount", type="integer", description="결제 금액", example="10000"),
     *       @OA\Property(
     *         property="subscribed_at",
     *         type="string",
     *         description="정기 결제 등록 일시(ISO 8601 Format)",
     *         example="2018-06-07T01:59:30+09:00"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/UnauthorizedPartner")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/UnregisteredPaymentMethod")
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
    public function subscribe(Request $request): JsonResponse
    {
        try {
            ApiSecretValidator::validate($request);

            $body = json_decode($request->getContent());
            $result = SubscriptionAppService::subscribe(
                ApiSecretValidator::getApiKey($request),
                ApiSecretValidator::getSecretKey($request),
                $body->payment_method_id,
                $body->product_name,
                $body->amount
            );
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            return self::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (UnregisteredPaymentMethodException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse([
            'subscription_id' => $result->subscription_id,
            'payment_method_id' => $result->payment_method_id,
            'product_name' => $result->product_name,
            'amount' => $result->amount,
            'subscribed_at' => $result->subscribed_at->format(DATE_ATOM)
        ]);
    }

    /**
     * @Route(
     *   "/payments/subscriptions/{subscription_id}/pay",
     *   methods={"POST"},
     *   requirements={
     *     "bill_key"="[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}"
     *   }
     * )
     * @ParamValidator(
     *   {"param"="partner_transaction_id", "constraints"={"NotBlank", {"Type"="string"}}}
     * )
     *
     * @OA\Post(
     *   path="/payments/subscriptions/{subscription_id}/pay",
     *   summary="정기 결제 승인",
     *   tags={"public-api"},
     *   @OA\Parameter(ref="#/components/parameters/Api-Key"),
     *   @OA\Parameter(ref="#/components/parameters/Secret-Key"),
     *   @OA\Parameter(
     *     name="subscription_id",
     *     in="path",
     *     required=true,
     *     description="RIDI Pay 정기 결제 ID",
     *     example="550E8400-E29B-41D4-A716-446655440000",
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"partner_transaction_id"},
     *       @OA\Property(property="partner_transaction_id", type="string", description="가맹점 주문 번호")
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
     *         "subscription_id",
     *         "product_name",
     *         "amount",
     *         "subscribed_at",
     *         "approved_at",
     *       },
     *       @OA\Property(
     *         property="transaction_id",
     *         type="string",
     *         description="RIDI Pay 주문 번호",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       ),
     *       @OA\Property(property="partner_transaction_id", type="string", description="가맹점 주문 번호"),
     *       @OA\Property(
     *         property="subscription_id",
     *         type="string",
     *         description="RIDI Pay 정기 결제 ID",
     *         example="880E8200-A29B-24B2-8716-42B65544A000"
     *       ),
     *       @OA\Property(property="product_name", type="string", description="결제 상품", example="리디북스 전자책"),
     *       @OA\Property(property="amount", type="integer", description="결제 금액", example="10000"),
     *       @OA\Property(
     *         property="subscribed_at",
     *         type="string",
     *         description="정기 결제 등록 일시(ISO 8601 Format)",
     *         example="2018-06-07T01:59:30+09:00"
     *       ),
     *       @OA\Property(
     *         property="approved_at",
     *         type="string",
     *         description="정기 결제 승인 일시(ISO 8601 Format)",
     *         example="2018-06-07T03:30:30+09:00"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/UnauthorizedPartner")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/UnregisteredPaymentMethod")
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
     *
     * @param Request $request
     * @param string $subscription_id
     * @return JsonResponse
     */
    public function paySubscription(Request $request, string $subscription_id): JsonResponse
    {
        try {
            ApiSecretValidator::validate($request);

            $body = json_decode($request->getContent());
            $result = SubscriptionAppService::paySubscription(
                ApiSecretValidator::getApiKey($request),
                ApiSecretValidator::getSecretKey($request),
                $subscription_id,
                $body->partner_transaction_id
            );
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            return self::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (UnregisteredPaymentMethodException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (TransactionApprovalException $e) {
            return self::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::TRANSACTION_APPROVAL_FAILED,
                $e->getMessage(),
                ['pg_message' => $e->getPgMessage()]
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse([
            'subscription_id' => $result->subscription_id,
            'transaction_id' => $result->transaction_id,
            'partner_transaction_id' => $result->partner_transaction_id,
            'product_name' => $result->product_name,
            'amount' => $result->amount,
            'subscribed_at' => $result->subscribed_at->format(DATE_ATOM),
            'approved_at' => $result->approved_at->format(DATE_ATOM)
        ]);
    }
}
