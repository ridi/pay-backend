<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\User\Domain\Exception\AlreadyHadCardException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\UnregisteredUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Application\Service\CardAppService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentMethodController extends BaseController
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
        if ($u_id !== $this->getUid()) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $body = json_decode($request->getContent());
            CardAppService::registerCard(
                $this->getUidx(),
                $body->card_number,
                $body->card_expiration_date,
                $body->card_password,
                $body->tax_id
            );
        } catch (AlreadyHadCardException $e) {
            return self::createErrorResponse(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::createSuccessResponse();
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
        if ($u_id !== $this->getUid()) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        }

        try {
            CardAppService::deleteCard($this->getUidx(), $payment_method_id);
        } catch (UnregisteredUserException | LeavedUserException | UnregisteredPaymentMethodException $e) {
            return self::createErrorResponse(Response::HTTP_NOT_FOUND, $e->getMessage());
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::createSuccessResponse();
    }
}
