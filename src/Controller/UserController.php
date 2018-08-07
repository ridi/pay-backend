<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\User\Service\PaymentMethodService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends Controller
{
    /**
     * @Route("/users/{u_id}/payment-methods", methods={"GET"})
     * @param string $u_id
     * @return JsonResponse
     */
    public function getPaymentMethods(string $u_id): JsonResponse
    {
        $u_idx = 0; // TODO: u_idx 값 얻기

        try {
            $payment_methods = PaymentMethodService::getPaymentMethods($u_idx);
        } catch (\Exception $e) {
            return new JsonResponse(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['payment_methods' => $payment_methods]);
    }
}
