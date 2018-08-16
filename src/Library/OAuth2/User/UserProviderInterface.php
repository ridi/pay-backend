<?php
declare(strict_types=1);

namespace RidiPay\Library\OAuth2\User;

use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use Ridibooks\OAuth2\Authorization\Token\JwtToken;
use Symfony\Component\HttpFoundation\Request;

interface UserProviderInterface
{
    /**
     * @param JwtToken $token
     * @param Request $request
     * @return User
     * @throws AuthorizationException
     */
    public function getUser(JwtToken $token, Request $request): User;
}
