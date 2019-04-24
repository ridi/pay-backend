<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use RidiPay\Controller\Logger\ControllerAccessLogger;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\PartnerErrorCodeConstant;
use RidiPay\Controller\Response\PgErrorCodeConstant;
use RidiPay\Controller\Response\TransactionErrorCodeConstant;
use RidiPay\Library\SentryHelper;
use RidiPay\Library\Validation\ApiSecretValidationException;
use RidiPay\Library\Validation\ApiSecretValidator;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Pg\Domain\Exception\TransactionCancellationException;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use RidiPay\Transaction\Domain\Exception\AlreadyCancelledTransactionException;
use RidiPay\Transaction\Domain\Exception\NotFoundTransactionException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends BaseController
{
    /**
     * @Route(
     *   "/payments/{transaction_id}/cancel",
     *   methods={"POST"},
     *   requirements={
     *     "transaction_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
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
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/AlreadyCancelledTransaction"),
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
        if ($request->getContentType() !== self::REQUEST_CONTENT_TYPE) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INVALID_CONTENT_TYPE
            );
        }

        ControllerAccessLogger::logRequest($request);

        try {
            $partner_api_secret = ApiSecretValidator::validate($request);

            $result = TransactionAppService::cancelTransaction($partner_api_secret, $transaction_id);

            $response = self::createSuccessResponse([
                'transaction_id' => $result->transaction_id,
                'partner_transaction_id' => $result->partner_transaction_id,
                'product_name' => $result->product_name,
                'amount' => $result->amount,
                'reserved_at' => $result->reserved_at->format(DATE_ATOM),
                'approved_at' => $result->approved_at->format(DATE_ATOM),
                'canceled_at' => $result->canceled_at->format(DATE_ATOM)
            ]);
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            $response = self::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotFoundTransactionException $e) {
            $response = self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_FOUND_TRANSACTION,
                $e->getMessage()
            );
        } catch (AlreadyCancelledTransactionException $e) {
            $response = self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::ALREADY_CANCELLED_TRANSACTION,
                $e->getMessage()
            );
        } catch (TransactionCancellationException $e) {
            $response = self::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::TRANSACTION_CANCELLATION_FAILED,
                $e->getMessage(),
                ['pg_message' => $e->getPgMessage()]
            );
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
     * @Route(
     *   "/payments/{transaction_id}/status",
     *   methods={"GET"},
     *   requirements={
     *     "transaction_id"="^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-4[0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}$"
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
        ControllerAccessLogger::logRequest($request);

        try {
            $partner_api_secret = ApiSecretValidator::validate($request);

            $result = TransactionAppService::getTransactionStatus($partner_api_secret, $transaction_id);

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
            $response = self::createSuccessResponse($data);
        } catch (ApiSecretValidationException | UnauthorizedPartnerException $e) {
            $response = self::createErrorResponse(
                PartnerErrorCodeConstant::class,
                PartnerErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotFoundTransactionException $e) {
            $response = self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_FOUND_TRANSACTION,
                $e->getMessage()
            );
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
