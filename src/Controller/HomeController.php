<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use Predis\Client;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Library\ConnectionProvider;
use RidiPay\Library\SentryHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends BaseController
{
    /**
     * @Route("/health-check", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $db = ConnectionProvider::getConnection();
            if (!$db->ping()) {
                throw new \Exception('MariaDB connection is not working.');
            }

            $redis = new Client(['host' => getenv('REDIS_HOST', true)]);
            if (!$redis->ping()) {
                throw new \Exception('Redis connection is not working.');
            }
        } catch (\Throwable $t) {
            SentryHelper::captureException($t);

            return self::createErrorResponse(
                CommonErrorCodeConstant::class,
                CommonErrorCodeConstant::INTERNAL_SERVER_ERROR
            );
        }

        return self::createSuccessResponse();
    }
}
