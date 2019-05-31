<?php
declare(strict_types=1);

namespace RidiPay\Tests\Dummy;

use RidiPay\Library\Cors\Annotation\Cors;
use RidiPay\Library\Jwt\Annotation\JwtAuth;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DummyController extends Controller
{
    /**
     * @Route("/jwt-auth", methods={"GET"})
     * @JwtAuth(isses={"dummy"})
     *
     * @param Request $request
     * @return Response
     */
    public function jwtAuthTest(Request $request): Response
    {
        return new Response();
    }

    /**
     * @Route("/param-validator", methods={"POST"})
     * @ParamValidator(
     *   rules={
     *     {"param"="digits", "constraints"={{"Regex"="/^\d+$/"}}},
     *     {"param"="not_blank_string", "constraints"={"NotBlank", {"Type"="string"}}},
     *     {"param"="boolean", "constraints"={{"Type"="bool"}}},
     *     {"param"="uuid", "constraints"={"Uuid"}},
     *     {"param"="url", "constraints"={"Url"}},
     *     {"param"="card_number", "constraints"={{"Regex"="/^\d{13,16}$/"}}},
     *     {"param"="card_expiration_date", "constraints"={{"Regex"="/^\d{2}(0[1-9]|1[0-2])$/"}}},
     *     {"param"="tax_id", "constraints"={{"Regex"="/^(\d{2}(0[1-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1]))|\d{10}$/"}}},
     *     {"param"="email", "constraints"={"Email"}}
     *   }
     * )
     *
     * @param Request $request
     * @return Response
     */
    public function paramValidatorTest(Request $request): Response
    {
        return new Response();
    }

    /**
     * @Route("/cors", methods={"GET", "OPTIONS"})
     * @Cors(methods={"GET", "OPTIONS"})
     *
     * @param Request $request
     * @return Response
     */
    public function corsTest(Request $request): Response
    {
        return new Response();
    }

    /**
     * @Route("/success", methods={"GET"})
     *
     * @param Request $request
     * @return Response
     */
    public function success(Request $request): Response
    {
        return new Response();
    }

    /**
     * @Route("/exception-throwed", methods={"GET"})
     *
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function exceptionThrowed(Request $request): Response
    {
        throw new \Exception();

        return new Response();
    }
}
