<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\User\Exception\LeavedUserException;
use RidiPay\User\Exception\NonUserException;
use RidiPay\User\Exception\OnetouchPaySettingException;
use RidiPay\User\Exception\UnmatchedPinException;
use RidiPay\User\Exception\WrongPinException;
use RidiPay\User\Service\PaymentMethodService;
use RidiPay\User\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * @Route("/users/{u_id}/pin", methods={"PUT"})
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function updatePin(Request $request, string $u_id)
    {
        $u_idx = 0; // TODO: u_idx 값 얻기

        $body = json_decode($request->getContent());
        if (is_null($body)
            || !property_exists($body, 'pin')
        ) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        try {
            UserService::updatePin($u_idx, $body->pin);
        } catch (NonUserException | LeavedUserException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (WrongPinException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $t) {
            return new JsonResponse(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }

    /**
     * @Route("/users/{u_id}/pin/validate", methods={"POST"})
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function validatePin(Request $request, string $u_id)
    {
        $u_idx = 0; // TODO: u_idx 값 얻기

        $body = json_decode($request->getContent());
        if (is_null($body)
            || !property_exists($body, 'pin')
        ) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        try {
            UserService::validatePin($u_idx, $body->pin);
        } catch (NonUserException | LeavedUserException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (UnmatchedPinException $e) {
            return new JsonResponse(['message' => $e->getMessage()], response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }

    /**
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function updateOnetouchPay(Request $request, string $u_id)
    {
        $u_idx = 0; // TODO: u_idx 값 얻기

        $body = json_decode($request->getContent());
        if (is_null($body)
            || !property_exists($body, 'enable_onetouch_pay')
        ) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        try {
            if ($body->enable_onetouch_pay) {
                UserService::enableOnetouchPay($u_idx);
            } else {
                UserService::disableOnetouchPay($u_idx);
            }
        } catch (NonUserException | LeavedUserException $e) {
            return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (OnetouchPaySettingException $e) {
            return new JsonResponse(['message' => $e->getMessage()], response::HTTP_FORBIDDEN);
        } catch (\Throwable $t) {
            return new JsonResponse(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }
}
