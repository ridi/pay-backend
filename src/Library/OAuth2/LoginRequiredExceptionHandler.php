<?php
declare(strict_types=1);

namespace RidiPay\Library\OAuth2;

use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use Ridibooks\OAuth2\Authorization\Exception\InsufficientScopeException;
use Ridibooks\OAuth2\Symfony\Handler\OAuth2ExceptionHandlerInterface;
use Ridibooks\OAuth2\Symfony\Provider\OAuth2ServiceProvider;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginRequiredExceptionHandler implements OAuth2ExceptionHandlerInterface
{
    /**
     * @param AuthorizationException $e
     * @param Request $request
     * @param OAuth2ServiceProvider $oauth2_service_provider
     * @return null|Response
     */
    public function handle(
        AuthorizationException $e,
        Request $request,
        OAuth2ServiceProvider $oauth2_service_provider
    ): ?Response {
        if ($e instanceof InsufficientScopeException) {
            return new JsonResponse(
                [
                    'code' => CommonErrorCodeConstant::INVALID_ACCESS_TOKEN,
                    'message' => 'No sufficient scope.'
                ],
                Response::HTTP_FORBIDDEN
            );
        }

        return new JsonResponse(
            [
                'code' => CommonErrorCodeConstant::LOGIN_REQUIRED,
                'message' => 'Login Required.'
            ],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
