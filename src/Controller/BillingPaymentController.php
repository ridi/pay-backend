<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use RidiPay\Controller\Logger\ControllerAccessLogger;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\PartnerErrorCodeConstant;
use RidiPay\Controller\Response\PgErrorCodeConstant;
use RidiPay\Controller\Response\TransactionErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\DuplicatedRequestException;
use RidiPay\Library\Pg\Kcp\UnderMinimumPaymentAmountException;
use RidiPay\Library\SentryHelper;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Library\Validation\ApiSecretValidationException;
use RidiPay\Library\Validation\ApiSecretValidator;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Transaction\Application\Service\SubscriptionAppService;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledSubscriptionException;
use RidiPay\Transaction\Domain\Exception\AlreadyResumedSubscriptionException;
use RidiPay\Transaction\Domain\Exception\NotFoundSubscriptionException;
use RidiPay\User\Domain\Exception\DeletedPaymentMethodException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class BillingPaymentController extends BaseController
{
    /**
     * @Route("/payments/subscriptions", methods={"POST"})
     * @ParamValidator(
     *   rules={
     *     {"param"="payment_method_id", "constraints"={"Uuid"}},
     *     {"param"="product_name", "constraints"={"NotBlank", {"Type"="string"}}}
     *   }
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
     *       required={"payment_method_id", "product_name"},
     *       @OA\Property(
     *         property="payment_method_id",
     *         type="string",
     *         description="RIDI Pay 결제 수단 ID",
     *         example="550E8400-E29B-41D4-A716-446655440000"
     *       ),
     *       @OA\Property(property="product_name", type="string", description="결제 상품", example="리디북스 전자책")
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       required={
     *         "subscription_id",
     *         "product_name",
     *         "subscribed_at"
     *       },
     *       @OA\Property(
     *         property="subscription_id",
     *         type="string",
     *         description="RIDI Pay 정기 결제 ID",
     *         example="880E8200-A29B-24B2-8716-42B65544A000"
     *       ),
     *       @OA\Property(property="product_name", type="string", description="결제 상품", example="리디북스 전자책"),
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
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/DeletedPaymentMethod")
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
            $result = SubscriptionAppService::subscribe(
                $partner_api_secret,
                $body->payment_method_id,
                $body->product_name
            );

            $response = BaseController::createSuccessResponse([
                'subscription_id' => $result->subscription_id,
                'product_name' => $result->product_name,
                'subscribed_at' => $result->subscribed_at->format(DATE_ATOM)
            ]);
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
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            $response = BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }

    /**
     * @Route("/payments/subscriptions/{subscription_id}",
     *   methods={"DELETE"},
     *   requirements={
     *     "subscription_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
     *   }
     * )
     *
     * @OA\Delete(
     *   path="/payments/subscriptions/{subscription_id}",
     *   summary="정기 결제 해지",
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
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       required={
     *         "subscription_id",
     *         "product_name",
     *         "subscribed_at",
     *         "unsubscribed_at",
     *       },
     *       @OA\Property(
     *         property="subscription_id",
     *         type="string",
     *         description="RIDI Pay 정기 결제 ID",
     *         example="880E8200-A29B-24B2-8716-42B65544A000"
     *       ),
     *       @OA\Property(property="product_name", type="string", description="결제 상품", example="리디북스 전자책"),
     *       @OA\Property(
     *         property="subscribed_at",
     *         type="string",
     *         description="정기 결제 등록 일시(ISO 8601 Format)",
     *         example="2018-06-07T01:59:30+09:00"
     *       ),
     *       @OA\Property(
     *         property="unsubscribed_at",
     *         type="string",
     *         description="정기 결제 해지 일시(ISO 8601 Format)",
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
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/AlreadyCancelledSubscription")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(ref="#/components/schemas/NotFoundSubscription")
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
    public function unsubscribe(Request $request, string $subscription_id): JsonResponse
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

            $result = SubscriptionAppService::unsubscribe($partner_api_secret, $subscription_id);

            $response = BaseController::createSuccessResponse([
                'subscription_id' => $result->subscription_id,
                'product_name' => $result->product_name,
                'subscribed_at' => $result->subscribed_at->format(DATE_ATOM),
                'unsubscribed_at' => $result->unsubscribed_at->format(DATE_ATOM)
            ]);
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            $response = BaseController::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotFoundSubscriptionException $e) {
            $response = BaseController::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_FOUND_TRANSACTION,
                $e->getMessage()
            );
        } catch (AlreadyCancelledSubscriptionException $e) {
            $response = BaseController::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::ALREADY_CANCELLED_SUBSCRIPTION,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            $response = BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }

    /**
     * @Route("/payments/subscriptions/{subscription_id}/resume",
     *   methods={"PUT"},
     *   requirements={
     *     "subscription_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
     *   }
     * )
     *
     * @OA\Put(
     *   path="/payments/subscriptions/{subscription_id}/resume",
     *   summary="정기 결제 해지 취소",
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
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(
     *       type="object",
     *       required={
     *         "subscription_id",
     *         "product_name",
     *         "subscribed_at",
     *       },
     *       @OA\Property(
     *         property="subscription_id",
     *         type="string",
     *         description="RIDI Pay 정기 결제 ID",
     *         example="880E8200-A29B-24B2-8716-42B65544A000"
     *       ),
     *       @OA\Property(property="product_name", type="string", description="결제 상품", example="리디북스 전자책"),
     *       @OA\Property(
     *         property="subscribed_at",
     *         type="string",
     *         description="정기 결제 등록 일시(ISO 8601 Format)",
     *         example="2018-06-07T01:59:30+09:00"
     *       ),
     *     )
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/UnauthorizedPartner")
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/AlreadyResumedSubscription"),
     *         @OA\Schema(ref="#/components/schemas/DeletedPaymentMethod")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotFoundSubscription"),
     *         @OA\Schema(ref="#/components/schemas/UnregisteredPaymentMethod")
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
     * @param string $subscription_id
     * @return JsonResponse
     */
    public function resumeSubscription(Request $request, string $subscription_id): JsonResponse
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

            $result = SubscriptionAppService::resumeSubscription($partner_api_secret, $subscription_id);

            $response = BaseController::createSuccessResponse([
                'subscription_id' => $result->subscription_id,
                'product_name' => $result->product_name,
                'subscribed_at' => $result->subscribed_at->format(DATE_ATOM)
            ]);
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            $response = BaseController::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotFoundSubscriptionException $e) {
            $response = BaseController::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_FOUND_TRANSACTION,
                $e->getMessage()
            );
        } catch (DeletedPaymentMethodException $e) {
            $response = BaseController::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::DELETED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (UnregisteredPaymentMethodException $e) {
            $response = BaseController::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD,
                $e->getMessage()
            );
        } catch (AlreadyResumedSubscriptionException $e) {
            $response = BaseController::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::ALREADY_RESUMED_SUBSCRIPTION,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

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
     *   "/payments/subscriptions/{subscription_id}/pay",
     *   methods={"POST"},
     *   requirements={
     *     "subscription_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
     *   }
     * )
     * @ParamValidator(
     *   rules={
     *     {"param"="partner_transaction_id", "constraints"={"NotBlank", {"Type"="string"}}},
     *     {"param"="amount", "constraints"={{"Regex"="/^\d+$/"}}},
     *     {"param"="buyer_id", "constraints"={"NotBlank", {"Type"="string"}}},
     *     {"param"="buyer_name", "constraints"={"NotBlank", {"Type"="string"}}},
     *     {"param"="buyer_email", "constraints"={"Email"}},
     *     {"param"="invoice_id", "constraints"={"NotBlank", {"Type"="string"}}},
     *   }
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
     *       required={"partner_transaction_id", "buyer_id", "buyer_name", "buyer_email", "invoice_id"},
     *       @OA\Property(property="partner_transaction_id", type="string", description="가맹점 주문 번호"),
     *       @OA\Property(property="buyer_id", type="string", description="구매자 ID(가맹점)"),
     *       @OA\Property(property="buyer_name", type="string", description="구매자 이름"),
     *       @OA\Property(property="buyer_email", type="string", description="구매자 Email"),
     *       @OA\Property(
     *         property="invoice_id",
     *         type="string",
     *         description="가맹점에서 중복 정기 결제를 방지하기 위해서 입력하는 Identifier"
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
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(ref="#/components/schemas/UnderMinimumPaymentAmount")
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(ref="#/components/schemas/UnauthorizedPartner")
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       oneOf={
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
     *         @OA\Schema(ref="#/components/schemas/NotFoundSubscription"),
     *         @OA\Schema(ref="#/components/schemas/UnregisteredPaymentMethod")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InternalServerError"),
     *         @OA\Schema(ref="#/components/schemas/TransactionApprovalFailed")
     *       }
     *     )
     *   )
     * )
     *
     * @param Request $request
     * @param string $subscription_id
     * @return JsonResponse
     */
    public function paySubscription(Request $request, string $subscription_id): JsonResponse
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
            $result = SubscriptionAppService::paySubscription(
                $partner_api_secret,
                $subscription_id,
                $body->partner_transaction_id,
                intval($body->amount),
                $body->buyer_id,
                $body->buyer_name,
                $body->buyer_email,
                $body->invoice_id
            );

            $response = BaseController::createSuccessResponse([
                'subscription_id' => $result->subscription_id,
                'transaction_id' => $result->transaction_id,
                'partner_transaction_id' => $result->partner_transaction_id,
                'product_name' => $result->product_name,
                'amount' => $result->amount,
                'subscribed_at' => $result->subscribed_at->format(DATE_ATOM),
                'approved_at' => $result->approved_at->format(DATE_ATOM)
            ]);
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            $response = BaseController::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotFoundSubscriptionException $e) {
            $response = BaseController::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_FOUND_SUBSCRIPTION,
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
        } catch (UnregisteredPaymentMethodException $e) {
            $response = BaseController::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD,
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
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            $response = BaseController::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        ControllerAccessLogger::logResponse($request, $response);

        return $response;
    }
}
