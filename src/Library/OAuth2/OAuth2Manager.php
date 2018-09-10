<?php
declare(strict_types=1);

namespace RidiPay\Library\OAuth2;

use Ridibooks\OAuth2\Authorization\Authorizer;
use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use Ridibooks\OAuth2\Authorization\Token\JwtToken;
use Ridibooks\OAuth2\Authorization\Validator\JwtTokenValidator;
use Ridibooks\OAuth2\Grant\DataTransferObject\AuthorizationServerInfo;
use Ridibooks\OAuth2\Grant\DataTransferObject\ClientInfo;
use Ridibooks\OAuth2\Grant\Granter;
use RidiPay\Library\OAuth2\User\DefaultUserProvider;
use RidiPay\Library\OAuth2\User\User;
use RidiPay\Library\TimeUnitConstant;
use Symfony\Component\HttpFoundation\Request;

class OAuth2Manager
{
    private const JWT_ALGORITHM = 'HS256';
    private const JWT_EXPIRE_TERM = 5 * TimeUnitConstant::SEC_IN_MINUTE;

    private const AUTHORIZATION_URL_PATH = '/ridi/authorize/';
    private const TOKEN_URL_PATH = '/oauth2/token/';

    /** @var string */
    private $account_server_host;

    /** @var Granter */
    private $granter;

    /** @var Authorizer */
    private $authorizer;

    /** @var User */
    private $user;

    public function __construct()
    {
        $this->account_server_host = getenv('ACCOUNT_SERVER_HOST');

        $this->bindGranter();
        $this->bindAuthorizer();
    }

    /**
     * @return Granter
     */
    public function getGranter(): Granter
    {
        return $this->granter;
    }

    /**
     * @return Authorizer
     */
    public function getAuthorizer(): Authorizer
    {
        return $this->authorizer;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param JwtToken $token
     * @param Request $request
     * @throws AuthorizationException
     */
    public function loadUser(JwtToken $token, Request $request): void
    {
        $user_provider = new DefaultUserProvider($this->account_server_host);
        $this->user = $user_provider->getUser($token, $request);
    }

    private function bindGranter(): void
    {
        $client_id = getenv('OAUTH2_CLIENT_ID');
        $client_secret = getenv('OAUTH2_CLIENT_SECRET');
        $authorize_url = $this->account_server_host . self::AUTHORIZATION_URL_PATH;
        $token_url = $this->account_server_host . self::TOKEN_URL_PATH;

        $client_info = new ClientInfo($client_id, $client_secret);
        $auth_server_info = new AuthorizationServerInfo($authorize_url, $token_url);
        $this->granter = new Granter($client_info, $auth_server_info);
    }

    private function bindAuthorizer(): void
    {
        $jwt_secret = getenv('OAUTH2_JWT_SECRET');

        $jwt_token_validator = new JwtTokenValidator(
            $jwt_secret,
            self::JWT_ALGORITHM,
            self::JWT_EXPIRE_TERM
        );
        $this->authorizer = new Authorizer($jwt_token_validator);
    }
}
