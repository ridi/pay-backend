<?php
declare(strict_types=1);

namespace RidiPay\Tests\Controller;

use Ridibooks\OAuth2\Constant\AccessTokenConstant;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

abstract class ControllerTestCase extends WebTestCase
{
    /**
     * @param array $options
     * @param array $server
     * @return Client
     */
    protected static function createClientWithOAuth2AccessToken(array $options = [], array $server = []): Client
    {
        $cookie = new Cookie(
            AccessTokenConstant::ACCESS_TOKEN_COOKIE_KEY,
            getenv('OAUTH2_ACCESS_TOKEN', true)
        );
        $client = self::createClient($options, $server);
        $client->getCookieJar()->set($cookie);

        return $client;
    }
}
