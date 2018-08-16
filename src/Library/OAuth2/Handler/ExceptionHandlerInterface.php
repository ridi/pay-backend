<?php
declare(strict_types=1);

namespace RidiPay\Library\OAuth2\Handler;

use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ExceptionHandlerInterface
{
    /**
     * @param AuthorizationException $e
     * @param Request $request
     * @return Response|null
     */
    public function handle(AuthorizationException $e, Request $request): ?Response;
}
