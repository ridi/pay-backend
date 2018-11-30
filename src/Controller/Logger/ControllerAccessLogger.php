<?php
declare(strict_types=1);

namespace RidiPay\Controller\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ControllerAccessLogger extends Logger
{
    // client_ip request_http_method request_uri request_protocol_version request_body
    private const REQUEST_LOG_FORMAT = '%s %s %s %s %s';

    // client_ip request_http_method request_uri request_protocol_version request_body response_http_status_code response_body
    private const RESPONSE_LOG_FORMAT = '%s %s %s %s %s %d %s';

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

        $message = sprintf(
            self::REQUEST_LOG_FORMAT,
            $request->getClientIp(),
            $request->getMethod(),
            $request->getRequestUri(),
            $request->getProtocolVersion(),
            empty($request->getContent()) ? '-' : $request->getContent()
        );

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

        $message = sprintf(
            self::RESPONSE_LOG_FORMAT,
            $request->getClientIp(),
            $request->getMethod(),
            $request->getRequestUri(),
            $request->getProtocolVersion(),
            empty($request->getContent()) ? '-' : $request->getContent(),
            $response->getStatusCode(),
            $response->getContent()
        );

        return $logger->info($message, $context);
    }
}
