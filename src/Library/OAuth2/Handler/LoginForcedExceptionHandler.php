<?php
declare(strict_types=1);

namespace RidiPay\Library\OAuth2\Handler;

use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use Ridibooks\OAuth2\Authorization\Exception\InsufficientScopeException;
use Ridibooks\OAuth2\Grant\Granter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoginForcedExceptionHandler implements ExceptionHandlerInterface
{
    /** @var Granter */
    private $granter;

    /**
     * @param Granter $granter
     */
    public function __construct(Granter $granter)
    {
        $this->granter = $granter;
    }

    /**
     * @param AuthorizationException $e
     * @param Request $request
     * @return null|Response
     * @throws \Exception
     */
    public function handle(AuthorizationException $e, Request $request): ?Response
    {
        $redirect_uri = $request->getUri();
        $state = self::generateState();

        if ($e instanceof InsufficientScopeException) {
            $url = $this->granter->authorize($state, $redirect_uri, $e->getRequiredScopes());
        } else {
            $url = $this->granter->authorize($state, $redirect_uri);
        }

        return new RedirectResponse($url);
    }

    /**
     * @return string
     * @throws \Exception
     */
    private static function generateState(): string
    {
        return \bin2hex(\random_bytes(8));
    }
}
