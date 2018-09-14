<?php
declare(strict_types=1);

namespace RidiPay\Tests\Jwt;

use RidiPay\Library\Jwt\Annotation\JwtAuth;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DummyController extends Controller
{
    /**
     * @Route("/", methods={"GET"})
     * @JwtAuth()
     *
     * @param Request $request
     * @return Response
     */
    public function dummyAction(Request $request): Response
    {
        return new Response();
    }
}
