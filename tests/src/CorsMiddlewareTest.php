<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use RidiPay\Tests\Dummy\DummyKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddlewareTest extends WebTestCase
{
    /**
     * @param array $options
     * @return DummyKernel
     */
    protected static function createKernel(array $options = []): DummyKernel
    {
        return new DummyKernel(getenv('APP_ENV'), true);
    }

    /**
     * @dataProvider headerProvider
     *
     * @param array $header
     * @param null|string $access_control_allow_origin
     * @param null|string $access_control_allow_credentials
     */
    public function testMiddleware(
        array $header,
        ?string $access_control_allow_origin,
        ?string $access_control_allow_credentials
    ) {
        $client = self::createClient([], $header);
        $client->request(Request::METHOD_OPTIONS, '/cors');
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->assertSame('', $client->getResponse()->getContent());
        $this->assertSame(
            Request::METHOD_GET,
            $client->getResponse()->headers->get('Access-Control-Allow-Methods')
        );

        $client->request(Request::METHOD_GET, '/cors');
        $this->assertSame(
            $access_control_allow_origin,
            $client->getResponse()->headers->get('Access-Control-Allow-Origin')
        );
        $this->assertSame(
            $access_control_allow_credentials,
            $client->getResponse()->headers->get('Access-Control-Allow-Credentials')
        );
    }

    /**
     * @return array
     */
    public function headerProvider(): array
    {
        $ridi_pay_url = getenv('RIDI_PAY_URL');

        return [
            [['HTTP_Origin' => $ridi_pay_url], $ridi_pay_url, 'true'],
            [['HTTP_Origin' => 'https://wrong.io'], null, null]
        ];
    }
}
