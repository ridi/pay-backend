<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use Ridibooks\OAuth2\Symfony\Provider\OAuth2ServiceProvider;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController extends Controller
{
    /** @var OAuth2ServiceProvider */
    protected $oauth2_service_provider;

    /**
     * @param OAuth2ServiceProvider $oauth2_service_provider
     */
    public function __construct(OAuth2ServiceProvider $oauth2_service_provider)
    {
        $this->oauth2_service_provider = $oauth2_service_provider;
    }

    /**
     * @param array $data
     * @param array $headers
     * @return JsonResponse
     */
    protected static function createSuccessResponse(array $data = [], array $headers = []): JsonResponse
    {
        return new JsonResponse($data, Response::HTTP_OK, $headers);
    }

    /**
     * @param string $error_code_class
     * @param string $error_code
     * @param string $message
     * @param array $headers
     * @return JsonResponse
     */
    protected static function createErrorResponse(
        string $error_code_class,
        string $error_code,
        ?string $message = null,
        array $headers = []
    ): JsonResponse {
        try {
            $error_code_reflection_class = new \ReflectionClass($error_code_class);
            $http_status_codes = $error_code_reflection_class->getConstant('HTTP_STATUS_CODES');
            if (is_null($http_status_codes)) {
                throw new \ReflectionException();
            }

            if (isset($http_status_codes[$error_code])) {
                $http_status_code = $http_status_codes[$error_code];
            } else {
                $http_status_code = Response::HTTP_INTERNAL_SERVER_ERROR;
            }
            if (empty($message)) {
                $message = Response::$statusTexts[$http_status_code];
            }
        } catch (\ReflectionException $e) {
            $http_status_code = Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = Response::$statusTexts[$http_status_code];
        } finally {
            return new JsonResponse(
                [
                    'code' => $error_code,
                    'message' => $message
                ],
                $http_status_code,
                $headers
            );
        }
    }

    /**
     * @return int
     */
    protected function getUidx(): int
    {
        return $this->oauth2_service_provider->getMiddleware()->getUser()->getUidx();
    }

    /**
     * @return string
     */
    protected function getUid(): string
    {
        return $this->oauth2_service_provider->getMiddleware()->getUser()->getUid();
    }
}
