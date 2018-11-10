<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use Predis\Client;
use RidiPay\Library\ConnectionProvider;
use RidiPay\Library\SentryHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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

            $redis = new Client(['host' => getenv('REDIS_HOST')]);
            if (!$redis->ping()) {
                throw new \Exception('Redis connection is not working.');
            }
        } catch (\Throwable $t) {
            SentryHelper::captureMessage($t->getMessage(), [], [], true);

            return new JsonResponse(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse();
    }

    /**
     * Ignore requests for favicon.ico
     * @Route("/favicon.ico", methods={"GET"})
     *
     * @return JsonResponse
     */
    public function favicon(): JsonResponse
    {
        return new JsonResponse();
    }
}
