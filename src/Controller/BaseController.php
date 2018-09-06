<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use RidiPay\Library\OAuth2\OAuth2Manager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController extends Controller
{
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
     * @param int $status_code
     * @param string $message
     * @param array $headers
     * @return JsonResponse
     */
    protected static function createErrorResponse(
        int $status_code,
        ?string $message = null,
        array $headers = []
    ): JsonResponse {
        if (!isset(Response::$statusTexts[$status_code])) {
            $status_code = Response::HTTP_INTERNAL_SERVER_ERROR;
            $message = Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR];
        }

        if (is_null($message)) {
            $message = Response::$statusTexts[$status_code];
        }

        return new JsonResponse(['message' => $message], $status_code, $headers);
    }

    /**
     * @return int
     */
    protected function getUidx(): int
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);

        return $oauth2_manager->getUser()->getUidx();
    }

    /**
     * @return string
     */
    protected function getUid(): string
    {
        /** @var OAuth2Manager $oauth2_manager */
        $oauth2_manager = $this->container->get(OAuth2Manager::class);

        return $oauth2_manager->getUser()->getUid();
    }
}
