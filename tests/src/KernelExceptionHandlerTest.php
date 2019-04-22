<?php
declare(strict_types=1);

namespace RidiPay\Tests;

use RidiPay\Tests\Dummy\DummyKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class KernelExceptionHandlerTest extends WebTestCase
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
     * @dataProvider uriProvider
     *
     * @param string $uri
     * @param int $expected_response_status_code
     */
    public function testHandler(string $uri, int $expected_response_status_code)
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, $uri);
        $this->assertSame($expected_response_status_code, $client->getResponse()->getStatusCode());
    }

    /**
     * @return array
     */
    public function uriProvider(): array
    {
        return [
            ['/success', Response::HTTP_OK],
            ['/not-found', Response::HTTP_NOT_FOUND],
            ['/exception-throwed', Response::HTTP_INTERNAL_SERVER_ERROR]
        ];
    }
}
