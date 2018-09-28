<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Cors\Annotation\Cors;
use RidiPay\Library\Jwt\Annotation\JwtAuth;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Transaction\Application\Service\TransactionAppService;
use RidiPay\User\Domain\Exception\PasswordEntryBlockedException;
use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\OnetouchPaySettingChangeDeclinedException;
use RidiPay\User\Domain\Exception\UnmatchedPasswordException;
use RidiPay\User\Domain\Exception\UnmatchedPinException;
use RidiPay\User\Domain\Exception\WrongFormattedPinException;
use RidiPay\User\Application\Service\PaymentMethodAppService;
use RidiPay\User\Application\Service\UserAppService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends BaseController
{
    /**
     * @Route("/users/{u_idx}", methods={"DELETE"}, requirements={"u_idx"="\d+"})
     * @JwtAuth()
     *
     * @param int $u_idx
     * @return JsonResponse
     */
    public function deleteUser(int $u_idx): JsonResponse
    {
        try {
            UserAppService::deleteUser($u_idx);
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse();
    }

    /**
     * @Route("/users/{u_idx}/payment-methods", methods={"GET"}, requirements={"u_idx"="\d+"})
     * @JwtAuth()
     *
     * @param int $u_idx
     * @return JsonResponse
     */
    public function getPaymentMethods(int $u_idx): JsonResponse
    {
        try {
            $payment_methods = PaymentMethodAppService::getAvailablePaymentMethods($u_idx);
            foreach ($payment_methods->cards as $card) {
                unset($card->color);
                unset($card->logo_image_url);
                unset($card->subscriptions);
            }
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse(['cards' => $payment_methods->cards]);
    }

    /**
     * @Route("/me", methods={"GET", "OPTIONS"})
     * @OAuth2()
     * @Cors(methods={"GET", "OPTIONS"})
     *
     * @return JsonResponse
     */
    public function getMyInformation(): JsonResponse
    {
        try {
            $user_information = UserAppService::getUserInformation($this->getUidx());
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse([
            'payment_methods' => $user_information->payment_methods,
            'has_pin' => $user_information->has_pin,
            'is_using_onetouch_pay' => $user_information->is_using_onetouch_pay
        ]);
    }

    /**
     * @Route("/me/pin", methods={"PUT", "OPTIONS"})
     * @ParamValidator({"param"="pin", "constraints"={{"Regex"="/\d{6}/"}}})
     * @OAuth2()
     * @Cors(methods={"PUT", "OPTIONS"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePin(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            UserAppService::updatePin($this->getUidx(), $body->pin);
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (WrongFormattedPinException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::WRONG_FORMATTED_PIN,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/pin/validate", methods={"POST", "OPTIONS"})
     * @ParamValidator({"param"="pin", "constraints"={{"Regex"="/\d{6}/"}}})
     * @OAuth2()
     * @Cors(methods={"POST", "OPTIONS"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validatePin(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            UserAppService::validatePin($this->getUidx(), $body->pin);

            if (isset($body->reservation_id)) {
                $validation_token = TransactionAppService::generateValidationToken($body->reservation_id);
            }
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (UnmatchedPinException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PIN_UNMATCHED,
                $e->getMessage()
            );
        } catch (PasswordEntryBlockedException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PIN_ENTRY_BLOCKED,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        $data = [];
        if (isset($validation_token)) {
            $data['validation_token'] = $validation_token;
        }

        return self::createSuccessResponse($data);
    }

    /**
     * @Route("/me/password/validate", methods={"POST", "OPTIONS"})
     * @ParamValidator({"param"="password", "constraints"={"NotBlank", {"Type"="string"}}})
     * @OAuth2()
     * @Cors(methods={"POST", "OPTIONS"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validatePassword(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            UserAppService::validatePassword($this->getUidx(), $this->getUid(), $body->password);

            if (isset($body->reservation_id)) {
                $validation_token = TransactionAppService::generateValidationToken($body->reservation_id);
            }
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (UnmatchedPasswordException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PASSWORD_UNMATCHED,
                $e->getMessage()
            );
        } catch (PasswordEntryBlockedException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::PASSWORD_ENTRY_BLOCKED,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        $data = [];
        if (isset($validation_token)) {
            $data['validation_token'] = $validation_token;
        }

        return self::createSuccessResponse($data);
    }

    /**
     * @Route("/me/onetouch", methods={"PUT", "OPTIONS"})
     * @ParamValidator({"param"="enable_onetouch_pay", "constraints"={{"Type"="bool"}}})
     * @OAuth2()
     * @Cors(methods={"PUT", "OPTIONS"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateOnetouchPay(Request $request)
    {
        try {
            $body = json_decode($request->getContent());
            if ($body->enable_onetouch_pay) {
                UserAppService::enableOnetouchPay($this->getUidx());
            } else {
                UserAppService::disableOnetouchPay($this->getUidx());
            }
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (NotFoundUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::NOT_FOUND_USER,
                $e->getMessage()
            );
        } catch (OnetouchPaySettingChangeDeclinedException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::ONETOUCH_PAY_SETTING_CHANGE_DECLINED,
                $e->getMessage()
            );
        } catch (\Throwable $t) {
            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse();
    }
}
