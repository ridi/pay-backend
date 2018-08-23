<?php
declare(strict_types=1);

namespace RidiPay\Library\OAuth2\User;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\BadResponseException;
use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use Ridibooks\OAuth2\Authorization\Exception\TokenNotFoundException;
use Ridibooks\OAuth2\Authorization\Exception\UserNotFoundException;
use Ridibooks\OAuth2\Authorization\Token\JwtToken;
use Ridibooks\OAuth2\Constant\AccessTokenConstant;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DefaultUserProvider implements UserProviderInterface
{
    private const USER_INFO_URL_PATH = '/accounts/me/';

    /** @var string */
    private $user_info_url;

    /**
     * @param string $account_server_host
     */
    public function __construct(string $account_server_host)
    {
        $this->user_info_url = $account_server_host . self::USER_INFO_URL_PATH;
    }

    /**
     * @param JwtToken $token
     * @param Request $request
     * @return User
     * @throws AuthorizationException
     * @throws TokenNotFoundException
     * @throws UserNotFoundException
     */
    public function getUser(JwtToken $token, Request $request): User
    {
        $access_token = $request->cookies->get(AccessTokenConstant::ACCESS_TOKEN_COOKIE_KEY);
        if (is_null($access_token === null)) {
            throw new TokenNotFoundException();
        }

        $cookie_jar = CookieJar::fromArray(
            [AccessTokenConstant::ACCESS_TOKEN_COOKIE_KEY => $access_token],
            getenv('OAUTH2_ACCESS_TOKEN_COOKIE_DOMAIN')
        );

        try {
            $client = new Client();
            $response = $client->get($this->user_info_url, [
                'cookies' => $cookie_jar,
                'headers' => ['Accept' => 'application/json'],
            ]);
            $content = $response->getBody()->getContents();
        } catch (BadResponseException $e) {
            $status = $e->getResponse()->getStatusCode();
            if ($status === Response::HTTP_UNAUTHORIZED) {
                throw new AuthorizationException('Unauthorized access token');
            }
            if ($status === Response::HTTP_NOT_FOUND) {
                throw new UserNotFoundException();
            }
            throw new AuthorizationException($e->getMessage());
        } catch (\Exception $e) {
            throw new AuthorizationException($e->getMessage());
        }

        return new User($content);
    }
}
