<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\Library\OAuth2\OAuth2Manager;
use RidiPay\User\Exception\AlreadyCardAddedException;
use RidiPay\User\Exception\LeavedUserException;
use RidiPay\User\Exception\NonUserException;
use RidiPay\User\Exception\UnknownPaymentMethodException;
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
     * @OAuth2()
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function addCard(Request $request, string $u_id): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();
        if ($u_id !== $oauth2_manager->getUser()->getUid()) {
            return new JsonResponse(['message' => 'Login required'], Response::HTTP_UNAUTHORIZED);
        }

        $body = json_decode($request->getContent());
        if (is_null($body)
            || !property_exists($body, 'card_number')
            || !property_exists($body, 'card_expiration_date')
            || !property_exists($body, 'card_password')
            || !property_exists($body, 'tax_id')
        ) {
            return new JsonResponse(['message' => 'Invalid request'], Response::HTTP_BAD_REQUEST);
        }

        try {
            CardService::addCard(
                $u_idx,
                $body->card_number,
                $body->card_expiration_date,
                $body->card_password,
                $body->tax_id
            );
        } catch (LeavedUserException | AlreadyCardAddedException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }

    /**
     * @Route("/users/{u_id}/cards/{payment_method_id}", methods={"DELETE"})
     * @OAuth2()
     * @param string $u_id
     * @param string $payment_method_id
     * @return JsonResponse
     */
    public function deleteCard(string $u_id, string $payment_method_id): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();
        if ($u_id !== $oauth2_manager->getUser()->getUid()) {
            return new JsonResponse(['message' => 'Login required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            CardService::deleteCard($u_idx, $payment_method_id);
        } catch (NonUserException | LeavedUserException | UnknownPaymentMethodException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }
}
