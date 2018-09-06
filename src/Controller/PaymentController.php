<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Library\Validation\ApiSecretValidationException;
use RidiPay\Library\Validation\ApiSecretValidator;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * @OAuth2()
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
                $this->getUidx(),
                $body->payment_method_id,
                $body->partner_transaction_id,
                $body->product_name,
                intval($body->amount),
                $body->return_url
            );
        } catch (ApiSecretValidationException $e) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::createSuccessResponse(['reservation_id' => $reservation_id]);
    }

    /**
     * @Route("/payments/{reservation_id}", methods={"POST"})
     * @OAuth2()
     *
     * @param string $reservation_id
     * @return JsonResponse
     */
    public function createPayment(string $reservation_id): JsonResponse
    {
        try {
            $result = TransactionAppService::createTransaction($this->getUidx(), $reservation_id);
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::createSuccessResponse([
            'return_url' => $result->return_url . '?' . http_build_query(['transaction_id' => $result->transaction_id])
        ]);
    }

    /**
     * @Route("/payments/{transaction_id}/approve", methods={"POST"})
     * @OAuth2()
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
                $this->getUidx(),
                $transaction_id
            );
        } catch (ApiSecretValidationException $e) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
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
     * @OAuth2()
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
                $this->getUidx(),
                $transaction_id
            );
        } catch (ApiSecretValidationException $e) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
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
     * @OAuth2()
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
                $this->getUidx(),
                $transaction_id
            );
        } catch (ApiSecretValidationException $e) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $data = [
            'transaction_id' => $result->transaction_id,
            'partner_transaction_id' => $result->partner_transaction_id,
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
