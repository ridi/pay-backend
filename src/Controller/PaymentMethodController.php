<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use OpenApi\Annotations as OA;
use Ridibooks\OAuth2\Symfony\Annotation\OAuth2;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Controller\Response\PgErrorCodeConstant;
use RidiPay\Controller\Response\UserErrorCodeConstant;
use RidiPay\Library\Cors\Annotation\Cors;
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
     * @Route("/me/cards", methods={"OPTIONS"})
     * @Cors(methods={"POST"})
     *
     * @return JsonResponse
     */
    public function registerCardPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/cards", methods={"POST"})
     * @ParamValidator(
     *     {"param"="card_number", "constraints"={{"Regex"="/\d{13,16}/"}}},
     *     {"param"="card_expiration_date", "constraints"={{"Regex"="/\d{2}(0[1-9]|1[0-2])/"}}},
     *     {"param"="card_password", "constraints"={{"Regex"="/\d{2}/"}}},
     *     {"param"="tax_id", "constraints"={{"Regex"="/(\d{2}(0[1-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1]))|\d{10}/"}}}
     * )
     * @OAuth2()
     *
     * @OA\Post(
     *   path="/me/cards",
     *   summary="카드 등록",
     *   tags={"private-api"},
     *   @OA\RequestBody(
     *     @OA\JsonContent(
     *       type="object",
     *       required={"card_number", "card_password", "card_expiration_date", "tax_id"},
     *       @OA\Property(property="card", type="string", description="카드 번호", example="5416543210231427"),
     *       @OA\Property(property="card_expiration_date", type="string", description="카드 유효 기한(YYMM)", example="2111"),
     *       @OA\Property(property="card_password", type="string", description="카드 비밀번호 앞 2자리", example="12"),
     *       @OA\Property(property="tax_id", type="string", description="개인: 생년월일(YYMMDD) / 법인: 사업자 번호 10자리", example="940101")
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(type="object")
     *   ),
     *   @OA\Response(
     *     response="400",
     *     description="Bad Request",
     *     @OA\JsonContent(ref="#/components/schemas/InvalidParameter")
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidAccessToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/CardAlreadyExists"),
     *         @OA\Schema(ref="#/components/schemas/LeavedUser")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/CardRegistrationFailed"),
     *         @OA\Schema(ref="#/components/schemas/InternalServerError")
     *       }
     *     )
     *   )
     * )
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
     * @Route("/me/cards/{payment_method_id}", methods={"OPTIONS"})
     * @Cors(methods={"DELETE"})
     *
     * @return JsonResponse
     */
    public function deleteCardPreflight(): JsonResponse
    {
        return self::createSuccessResponse();
    }

    /**
     * @Route("/me/cards/{payment_method_id}", methods={"DELETE"})
     * @OAuth2()
     *
     * @OA\Delete(
     *   path="/me/cards/{payment_method_id}",
     *   summary="카드 삭제",
     *   tags={"private-api"},
     *   @OA\Parameter(
     *     name="payment_method_id",
     *     description="RIDI Pay 결제 수단 ID",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Success",
     *     @OA\JsonContent(type="object")
     *   ),
     *   @OA\Response(
     *     response="401",
     *     description="Unauthorized",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/InvalidAccessToken"),
     *         @OA\Schema(ref="#/components/schemas/LoginRequired")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="403",
     *     description="Forbidden",
     *     @OA\JsonContent(ref="#/components/schemas/LeavedUser")
     *   ),
     *   @OA\Response(
     *     response="404",
     *     description="Not Found",
     *     @OA\JsonContent(
     *       oneOf={
     *         @OA\Schema(ref="#/components/schemas/NotFoundUser"),
     *         @OA\Schema(ref="#/components/schemas/UnregisteredPaymentMethod")
     *       }
     *     )
     *   ),
     *   @OA\Response(
     *     response="500",
     *     description="Internal Server Error",
     *     @OA\JsonContent(ref="#/components/schemas/InternalServerError")
     *   )
     * )
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
