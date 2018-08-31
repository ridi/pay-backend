<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\Library\OAuth2\OAuth2Manager;
use RidiPay\Transaction\Service\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends Controller
{
    /**
     * @Route("/payments/reserve", methods={"POST"})
     * @OAuth2()
     * @param Request $request
     * @return JsonResponse
     */
    public function reservePayment(Request $request): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();

        $partner_api_key = $request->headers->get('Api-Key');
        $partner_secret_key = $request->headers->get('Secret-Key');
        $body = json_decode($request->getContent());

        if (is_null($partner_api_key)
            || is_null($partner_secret_key)
            || is_null($body)
            || !property_exists($body, 'payment_method_id')
            || !property_exists($body, 'partner_transaction_id')
            || !property_exists($body, 'product_name')
            || !property_exists($body, 'amount')
            || !property_exists($body, 'return_url')
        ) {
            return new JsonResponse(['message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $reservation_id = TransactionService::reserveTransaction(
                $partner_api_key,
                $partner_secret_key,
                $u_idx,
                $body->payment_method_id,
                $body->partner_transaction_id,
                $body->product_name,
                intval($body->amount),
                $body->return_url
            );
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['reservation_id' => $reservation_id]);
    }

    /**
     * @Route("/payments/{reservation_id}", methods={"POST"})
     * @OAuth2()
     * @param Request $request
     * @param string $reservation_id
     * @return JsonResponse
     */
    public function createPayment(Request $request, string $reservation_id): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();

        $partner_api_key = $request->headers->get('Api-Key');
        $partner_secret_key = $request->headers->get('Secret-Key');

        if (is_null($partner_api_key) || is_null($partner_secret_key)) {
            return new JsonResponse(['message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $return_url = TransactionService::createTransaction($reservation_id);
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['return_url' => $return_url]);
    }

    /**
     * @Route("/payments/{transaction_id}/approve", methods={"POST"})
     * @OAuth2()
     * @param Request $request
     * @param string $transaction_id
     * @return JsonResponse
     */
    public function approvePayment(Request $request, string $transaction_id): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();

        $partner_api_key = $request->headers->get('Api-Key');
        $partner_secret_key = $request->headers->get('Secret-Key');

        if (is_null($partner_api_key) || is_null($partner_secret_key)) {
            return new JsonResponse(['message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = TransactionService::approveTransaction(
                $partner_api_key,
                $partner_secret_key,
                $u_idx,
                $transaction_id
            );
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(json_encode($result), Response::HTTP_OK, [], true);
    }

    /**
     * @Route("/payments/{transaction_id}/cancel", methods={"POST"})
     * @OAuth2()
     * @param Request $request
     * @param string $transaction_id
     * @return JsonResponse
     */
    public function cancelPayment(Request $request, string $transaction_id): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();

        $partner_api_key = $request->headers->get('Api-Key');
        $partner_secret_key = $request->headers->get('Secret-Key');

        if (is_null($partner_api_key) || is_null($partner_secret_key)) {
            return new JsonResponse(['message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = TransactionService::cancelTransaction(
                $partner_api_key,
                $partner_secret_key,
                $u_idx,
                $transaction_id
            );
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(json_encode($result), Response::HTTP_OK, [], true);
    }

    /**
     * @Route("/payments/{transaction_id}/status", methods={"GET"})
     * @OAuth2()
     * @param Request $request
     * @param string $transaction_id
     * @return JsonResponse
     */
    public function showPaymentStatus(Request $request, string $transaction_id): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();

        $partner_api_key = $request->headers->get('Api-Key');
        $partner_secret_key = $request->headers->get('Secret-Key');

        if (is_null($partner_api_key) || is_null($partner_secret_key)) {
            return new JsonResponse(['message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = TransactionService::getTransactionStatus(
                $partner_api_key,
                $partner_secret_key,
                $u_idx,
                $transaction_id
            );
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(json_encode($result), Response::HTTP_OK, [], true);
    }
}
