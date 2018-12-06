<?php
declare(strict_types=1);

namespace RidiPay\Controller\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ControllerAccessLogger extends Logger
{
    /**
     * @param string $channel
     */
    public function __construct(string $channel)
    {
        parent::__construct($channel);

        $this->pushHandler(new StreamHandler('php://stdout'));
        $this->pushProcessor(new ControllerAccessLogSanitizationProcessor());
    }

    /**
     * @param Request $request
     * @param array $context
     * @return bool
     */
    public static function logRequest(Request $request, array $context = [])
    {
        $logger = new self('REQUEST');

        $data = [
            'client_ip' => $request->getClientIp(),
            'request_http_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'request_protocol_version' => $request->getProtocolVersion()
        ];
        if (!empty($request->getContent())) {
            $data['request_body'] = $request->getContent();
        }
        $message = json_encode($data);

        return $logger->info($message, $context);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $context
     * @return bool
     */
    public static function logResponse(Request $request, Response $response, array $context = [])
    {
        $logger = new self('RESPONSE');

        $data = [
            'client_ip' => $request->getClientIp(),
            'request_http_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'request_protocol_version' => $request->getProtocolVersion()
        ];
        if (!empty($request->getContent())) {
            $data['request_body'] = $request->getContent();
        }
        $data['response_http_status_code'] = $response->getStatusCode();
        $data['response_body'] = $response->getContent();
        $message = json_encode($data);

        return $logger->info($message, $context);
    }
}
