<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\Library\OAuth2\OAuth2Manager;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\User\Domain\Exception\AlreadyHadCardException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\UnregisteredUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Application\Service\CardAppService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CardController extends Controller
{
    /**
     * @Route("/users/{u_id}/cards", methods={"POST"})
     * @ParamValidator(
     *     {"param"="card_number", "constraints"={{"Regex"="/\d{13,16}/"}}},
     *     {"param"="card_expiration_date", "constraints"={{"Regex"="/\d{2}(0[1-9]|1[0-2])/"}}},
     *     {"param"="card_password", "constraints"={{"Regex"="/\d{2}/"}}},
     *     {"param"="tax_id", "constraints"={{"Regex"="/(\d{2}(0[1-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1]))|\d{10}/"}}}
     * )
     * @OAuth2()
     *
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function registerCard(Request $request, string $u_id): JsonResponse
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);
        $u_idx = $oauth2_manager->getUser()->getUidx();
        if ($u_id !== $oauth2_manager->getUser()->getUid()) {
            return new JsonResponse(['message' => 'Login required'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $body = json_decode($request->getContent());
            CardAppService::registerCard(
                $u_idx,
                $body->card_number,
                $body->card_expiration_date,
                $body->card_password,
                $body->tax_id
            );
        } catch (AlreadyHadCardException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }

    /**
     * @Route("/users/{u_id}/cards/{payment_method_id}", methods={"DELETE"})
     * @OAuth2()
     *
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
            CardAppService::deleteCard($u_idx, $payment_method_id);
        } catch (UnregisteredUserException | LeavedUserException | UnregisteredPaymentMethodException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $t) {
            return new JsonResponse(['message' => 'Internal Server Error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }
}
