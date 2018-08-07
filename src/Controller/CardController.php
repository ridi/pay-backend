<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\User\Service\CardService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CardController extends Controller
{
    /**
     * @Route("/users/{u_id}/cards", methods={"POST"})
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function addCard(Request $request, string $u_id): JsonResponse
    {
        $u_idx = 0; // TODO: u_idx 값 얻기

        $card_number = $request->get('card_number');
        $card_expiration_date = $request->get('card_expiration_date');
        $card_password = $request->get('card_password');
        $tax_id = $request->get('tax_id');

        try {
            CardService::addCard($u_idx, $card_number, $card_expiration_date, $card_password, $tax_id);
        } catch (\Exception $e) {
            return new JsonResponse(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }

    /**
     * @Route("/users/{u_id}/cards/{payment_method_id}", methods={"DELETE"})
     * @param string $u_id
     * @param string $payment_method_id
     * @return JsonResponse
     */
    public function deleteCard(string $u_id, string $payment_method_id): JsonResponse
    {
        $u_idx = 0; // TODO: u_idx 값 얻기

        try {
            CardService::deleteCard($u_idx, $payment_method_id);
        } catch (\Exception $e) {
            return new JsonResponse(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }
}
