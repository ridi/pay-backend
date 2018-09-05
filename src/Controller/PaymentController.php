<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\Library\OAuth2\OAuth2Manager;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends Controller
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
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();

        $partner_api_key = $request->headers->get('Api-Key');
        $partner_secret_key = $request->headers->get('Secret-Key');

        if (is_null($partner_api_key) || is_null($partner_secret_key)) {
            return new JsonResponse(['message' => "API Credentials don't exist"], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $body = json_decode($request->getContent());
            $reservation_id = TransactionAppService::reserveTransaction(
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
     *
     * @param string $reservation_id
     * @return JsonResponse
     */
    public function createPayment(string $reservation_id): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();

        try {
            $result = TransactionAppService::createTransaction($u_idx, $reservation_id);
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
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
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();

        $partner_api_key = $request->headers->get('Api-Key');
        $partner_secret_key = $request->headers->get('Secret-Key');

        if (is_null($partner_api_key) || is_null($partner_secret_key)) {
            return new JsonResponse(['message' => "API Credentials don't exist"], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $result = TransactionAppService::approveTransaction(
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
     *
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
            return new JsonResponse(['message' => "API Credentials don't exist"], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $result = TransactionAppService::cancelTransaction(
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
     *
     * @param Request $request
     * @param string $transaction_id
     * @return JsonResponse
     */
    public function getPaymentStatus(Request $request, string $transaction_id): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();

        $partner_api_key = $request->headers->get('Api-Key');
        $partner_secret_key = $request->headers->get('Secret-Key');

        if (is_null($partner_api_key) || is_null($partner_secret_key)) {
            return new JsonResponse(['message' => "API Credentials don't exist"], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $result = TransactionAppService::getTransactionStatus(
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
