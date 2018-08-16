<?php
declare(strict_types=1);

namespace RidiPay\Library\OAuth2\Handler;

use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use Ridibooks\OAuth2\Authorization\Exception\InsufficientScopeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginRequiredExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @param AuthorizationException $e
     * @param Request $request
     * @return null|Response
     */
    public function handle(AuthorizationException $e, Request $request): ?Response
    {
        if ($e instanceof InsufficientScopeException) {
            return new JsonResponse(['message' => 'No sufficient scope'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse(['message' => 'Login required'], Response::HTTP_UNAUTHORIZED);
    }
}
