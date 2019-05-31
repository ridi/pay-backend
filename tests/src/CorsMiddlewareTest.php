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
        return new DummyKernel(getenv('APP_ENV', true), true);
    }

    /**
     * @dataProvider headerProvider
     *
     * @param array $header
     * @param null|string $access_control_allow_origin
     * @param null|string $access_control_allow_methods
     * @param null|string $access_control_allow_credentials
     */
    public function testMiddleware(
        array $header,
        ?string $access_control_allow_origin,
        ?string $access_control_allow_methods,
        ?string $access_control_allow_credentials
    ) {
        $client = self::createClient([], $header);
        $client->request(Request::METHOD_OPTIONS, '/cors');
        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $this->assertSame('', $client->getResponse()->getContent());
        $this->assertValidAccessControlAllowOrigin(
            $access_control_allow_origin,
            $client->getResponse()
        );
        $this->assertValidAccessControlAllowCredentials(
            $access_control_allow_credentials,
            $client->getResponse()
        );
        $this->assertValidAccessControlAllowMethods(
            $access_control_allow_methods,
            $client->getResponse()
        );

        $client->request(Request::METHOD_GET, '/cors');
        $this->assertValidAccessControlAllowOrigin(
            $access_control_allow_origin,
            $client->getResponse()
        );
        $this->assertValidAccessControlAllowCredentials(
            $access_control_allow_credentials,
            $client->getResponse()
        );
    }

    /**
     * @return array
     */
    public function headerProvider(): array
    {
        $ridi_pay_url = getenv('RIDI_PAY_URL', true);

        return [
            [
                ['HTTP_Origin' => $ridi_pay_url],
                $ridi_pay_url,
                implode(', ', [Request::METHOD_GET, Request::METHOD_OPTIONS]),
                'true'
            ],
            [
                ['HTTP_Origin' => 'https://wrong.io'],
                null,
                null,
                null
            ]
        ];
    }

    /**
     * @param null|string $access_control_allow_origin
     * @param Response $response
     */
    private function assertValidAccessControlAllowOrigin(
        ?string $access_control_allow_origin,
        Response $response
    ): void {
        $this->assertSame(
            $access_control_allow_origin,
            $response->headers->get('Access-Control-Allow-Origin')
        );
    }

    /**
     * @param null|string $access_control_allow_credentials
     * @param Response $response
     */
    private function assertValidAccessControlAllowCredentials(
        ?string $access_control_allow_credentials,
        Response $response
    ): void {
        $this->assertSame(
            $access_control_allow_credentials,
            $response->headers->get('Access-Control-Allow-Credentials')
        );
    }

    /**
     * @param null|string $access_control_allow_methods
     * @param Response $response
     */
    private function assertValidAccessControlAllowMethods(
        ?string $access_control_allow_methods,
        Response $response
    ): void {
        $this->assertSame(
            $access_control_allow_methods,
            $response->headers->get('Access-Control-Allow-Methods')
        );
    }
}
