<?php
declare(strict_types=1);

namespace RidiPay\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends BaseController
{
    /**
     * @Route("/health-check", methods={"GET"})
     *
     * @return Response
     */
    public function healthCheck(): Response
    {
        return new Response("It's healthy");
    }

    /**
     * Ignore requests for favicon.ico
     * @Route("/favicon.ico", methods={"GET"})
     *
     * @return Response
     */
    public function favicon(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
