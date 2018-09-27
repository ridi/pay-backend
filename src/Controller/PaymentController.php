<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\PgErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Cors\Annotation\Cors;
use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Library\Validation\ApiSecretValidationException;
use RidiPay\Library\Validation\ApiSecretValidator;
use RidiPay\Pg\Domain\Exception\TransactionApprovalException;
use RidiPay\Pg\Domain\Exception\TransactionCancellationException;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use RidiPay\Transaction\Domain\Exception\NonexistentTransactionException;
use RidiPay\Transaction\Domain\Exception\NotReservedTransactionException;
use RidiPay\Partner\Domain\Exception\UnauthorizedPartnerException;
use RidiPay\Controller\Response\TransactionErrorCodeConstant;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends BaseController
{
    /**
     * @Route("/payments/reserve", methods={"POST"})
     * @ParamValidator(
     *     {"param"="payment_method_id", "constraints"={"Uuid"}},
     *     {"param"="partner_transaction_id", "constraints"={"Uuid"}},
     *     {"param"="product_name", "constraints"={"NotBlank", {"Type"="string"}}},
     *     {"param"="amount", "constraints"={{"Regex"="/\d+/"}}},
     *     {"param"="return_url", "constraints"={"Url"}}
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
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::UNAUTHORIZED_PARTNER,
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
     * @Route("/payments/{reservation_id}", methods={"POST", "OPTIONS"})
     * @OAuth2()
     * @Cors(methods={"POST"})
     *
     * @param string $reservation_id
     * @return JsonResponse
     */
    public function createPayment(string $reservation_id): JsonResponse
    {
        try {
            $result = TransactionAppService::createTransaction($this->getUidx(), $reservation_id);
        } catch (UnauthorizedPartnerException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NotReservedTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NOT_RESERVED_TRANSACTION,
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
            'return_url' => $result->return_url . '?' . http_build_query(['transaction_id' => $result->transaction_id])
        ]);
    }

    /**
     * @Route("/payments/{transaction_id}/approve", methods={"POST"})
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
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NonexistentTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NONEXISTENT_TRANSACTION,
                $e->getMessage()
            );
        } catch (TransactionApprovalException $e) {
            return self::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::TRANSACTION_APPROVAL_FAILED,
                $e->getMessage()
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
     * @Route("/payments/{transaction_id}/cancel", methods={"POST"})
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
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NonexistentTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NONEXISTENT_TRANSACTION,
                $e->getMessage()
            );
        } catch (TransactionCancellationException $e) {
            return self::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::TRANSACTION_CANCELLATION_FAILED,
                $e->getMessage()
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
     * @Route("/payments/{transaction_id}/status", methods={"GET"})
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
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::UNAUTHORIZED_PARTNER,
                $e->getMessage()
            );
        } catch (NonexistentTransactionException $e) {
            return self::createErrorResponse(
                TransactionErrorCodeConstant::class,
                TransactionErrorCodeConstant::NONEXISTENT_TRANSACTION,
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
}
