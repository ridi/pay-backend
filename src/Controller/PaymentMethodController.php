<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\PgErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Cors\Annotation\Cors;
use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use RidiPay\Pg\Domain\Exception\CardRegistrationException;
use RidiPay\User\Domain\Exception\CardAlreadyExistsException;
use RidiPay\User\Domain\Exception\LeavedUserException;
use RidiPay\User\Domain\Exception\NotFoundUserException;
use RidiPay\User\Domain\Exception\UnregisteredPaymentMethodException;
use RidiPay\User\Application\Service\CardAppService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PaymentMethodController extends BaseController
{
    /**
     * @Route("/me/cards", methods={"POST", "OPTIONS"})
     * @ParamValidator(
     *     {"param"="card_number", "constraints"={{"Regex"="/\d{13,16}/"}}},
     *     {"param"="card_expiration_date", "constraints"={{"Regex"="/\d{2}(0[1-9]|1[0-2])/"}}},
     *     {"param"="card_password", "constraints"={{"Regex"="/\d{2}/"}}},
     *     {"param"="tax_id", "constraints"={{"Regex"="/(\d{2}(0[1-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1]))|\d{10}/"}}}
     * )
     * @OAuth2()
     * @Cors(methods={"POST"})
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registerCard(Request $request): JsonResponse
    {
        try {
            $body = json_decode($request->getContent());
            CardAppService::registerCard(
                $this->getUidx(),
                $body->card_number,
                $body->card_expiration_date,
                $body->card_password,
                $body->tax_id
            );
        } catch (CardAlreadyExistsException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::CARD_ALREADY_EXISTS,
                $e->getMessage()
            );
        } catch (LeavedUserException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::LEAVED_USER,
                $e->getMessage()
            );
        } catch (CardRegistrationException $e) {
            return self::createErrorResponse(
                PgErrorCodeConstant::class,
                PgErrorCodeConstant::CARD_REGISTRATION_FAILED,
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
     * @Route("/me/cards/{payment_method_id}", methods={"DELETE", "OPTIONS"})
     * @OAuth2()
     * @Cors(methods={"DELETE"})
     *
     * @param string $payment_method_id
     * @return JsonResponse
     */
    public function deleteCard(string $payment_method_id): JsonResponse
    {
        try {
            CardAppService::deleteCard($this->getUidx(), $payment_method_id);
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
        } catch (UnregisteredPaymentMethodException $e) {
            return self::createErrorResponse(
                UserErrorCodeConstant::class,
                UserErrorCodeConstant::UNREGISTERED_PAYMENT_METHOD,
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
