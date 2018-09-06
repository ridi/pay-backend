<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Library\Jwt\Annotation\JwtAuth;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\User\Domain\Exception\PasswordEntryBlockedException;
use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\UnregisteredUserException;
use RidiPay\User\Domain\Exception\OnetouchPaySettingException;
use RidiPay\User\Domain\Exception\UnmatchedPasswordException;
use RidiPay\User\Domain\Exception\UnmatchedPinException;
use RidiPay\User\Domain\Exception\WrongPinException;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends BaseController
{
    /**
     * @Route("/users/{u_id}/payment-methods", methods={"GET"})
     * @OAuth2()
     * @JwtAuth()
     *
     * @param string $u_id
     * @return JsonResponse
     */
    public function getPaymentMethods(string $u_id): JsonResponse
    {
        if ($u_id !== $this->getUid()) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($this->getUidx());
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::createSuccessResponse(['payment_methods' => $payment_methods]);
    }

    /**
     * @Route("/users/{u_id}/pin", methods={"PUT"})
     * @ParamValidator({"param"="pin", "constraints"={{"Regex"="/\d{6}/"}}})
     * @OAuth2()
     *
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function updatePin(Request $request, string $u_id)
    {
        if ($u_id !== $this->getUid()) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $body = json_decode($request->getContent());
            UserAppService::updatePin($this->getUidx(), $body->pin);
        } catch (UnregisteredUserException | LeavedUserException $e) {
            return self::createErrorResponse(Response::HTTP_NOT_FOUND, $e->getMessage());
        } catch (WrongPinException $e) {
            return self::createErrorResponse(Response::HTTP_BAD_REQUEST, $e->getMessage());
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::createSuccessResponse();
    }

    /**
     * @Route("/users/{u_id}/pin/validate", methods={"POST"})
     * @ParamValidator({"param"="pin", "constraints"={{"Regex"="/\d{6}/"}}})
     * @OAuth2()
     *
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function validatePin(Request $request, string $u_id)
    {
        if ($u_id !== $this->getUid()) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $body = json_decode($request->getContent());
            UserAppService::validatePin($this->getUidx(), $body->pin);
        } catch (UnregisteredUserException | LeavedUserException $e) {
            return self::createErrorResponse(Response::HTTP_NOT_FOUND, $e->getMessage());
        } catch (UnmatchedPinException $e) {
            return self::createErrorResponse(Response::HTTP_BAD_REQUEST, $e->getMessage());
        } catch (PasswordEntryBlockedException $e) {
            return self::createErrorResponse(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::createSuccessResponse();
    }

    /**
     * @Route("/users/{u_id}/password/validate", methods={"POST"})
     * @ParamValidator({"param"="password", "constraints"={"NotBlank", {"Type"="string"}}})
     * @OAuth2()
     *
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function validatePassword(Request $request, string $u_id)
    {
        if ($u_id !== $this->getUid()) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $body = json_decode($request->getContent());
            UserAppService::validatePassword($this->getUidx(), $u_id, $body->password);
        } catch (UnregisteredUserException | LeavedUserException $e) {
            return self::createErrorResponse(Response::HTTP_NOT_FOUND, $e->getMessage());
        } catch (UnmatchedPasswordException $e) {
            return self::createErrorResponse(Response::HTTP_BAD_REQUEST, $e->getMessage());
        } catch (PasswordEntryBlockedException $e) {
            return self::createErrorResponse(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::createSuccessResponse();
    }

    /**
     * @Route("/users/{u_id}/onetouch", methods={"PUT"})
     * @ParamValidator({"param"="enable_onetouch_pay", "constraints"={{"Type"="bool"}}})
     * @OAuth2()
     *
     * @param Request $request
     * @param string $u_id
     * @return JsonResponse
     */
    public function updateOnetouchPay(Request $request, string $u_id)
    {
        if ($u_id !== $this->getUid()) {
            return self::createErrorResponse(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $u_idx = $this->getUidx();
            $body = json_decode($request->getContent());
            if ($body->enable_onetouch_pay) {
                UserAppService::enableOnetouchPay($u_idx);
            } else {
                UserAppService::disableOnetouchPay($u_idx);
            }
        } catch (UnregisteredUserException | LeavedUserException $e) {
            return self::createErrorResponse(Response::HTTP_NOT_FOUND, $e->getMessage());
        } catch (OnetouchPaySettingException $e) {
            return self::createErrorResponse(Response::HTTP_FORBIDDEN, $e->getMessage());
        } catch (\Throwable $t) {
            return self::createErrorResponse(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return self::createSuccessResponse();
    }
}
