<?php
declare(strict_types=1);

namespace RidiPay\Library\Jwt;

use Doctrine\Common\Annotations\CachedReader;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Library\Jwt\Annotation\JwtAuth;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class JwtAuthorizationMiddleware implements EventSubscriberInterface
{
    /** @var CachedReader */
    private $annotation_reader;

    /**
     * @param CachedReader $reader
     */
    public function __construct(CachedReader $reader)
    {
        $this->annotation_reader = $reader;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    /**
     * @param FilterControllerEvent $event
     * @throws \ReflectionException
     */
    public function onKernelController(FilterControllerEvent $event): void
    {
        if (!is_array($event->getController())) {
            return;
        }

        [$controller, $method_name] = $event->getController();
        $annotation = $this->getAnnotation($controller, $method_name);
        if (is_null($annotation)) {
            return;
        }

        try {
            self::authorize($event->getRequest(), $annotation->getIsses());
        } catch (\Exception $e) {
            $event->setController(function () use ($e) {
                return new JsonResponse(
                    [
                        'code' => CommonErrorCodeConstant::INVALID_JWT,
                        'message' => $e->getMessage()
                    ],
                    Response::HTTP_UNAUTHORIZED
                );
            });
        }

        return;
    }

    /**
     * @param Request $request
     * @param string[] $isses
     * @throws \Exception
     */
    private static function authorize(Request $request, array $isses)
    {
        $authorization_header = $request->headers->get('Authorization');
        if (is_null($authorization_header)) {
            throw new \Exception("Authorization header doesn't exist");
        }

        $jwt = sscanf($authorization_header, 'Bearer %s')[0];
        if (is_null($jwt)) {
            throw new \Exception('Invalid authorization header');
        }

        JwtAuthorizationHelper::decodeJwt($jwt, $isses, JwtAuthorizationServiceNameConstant::RIDI_PAY);
    }

    /**
     * @param mixed $controller
     * @param string $method_name
     * @return null|JwtAuth
     * @throws \ReflectionException
     */
    private function getAnnotation($controller, string $method_name): ?JwtAuth
    {
        $reflection_class = new \ReflectionClass($controller);
        $reflection_method = $reflection_class->getMethod($method_name);

        return $this->annotation_reader->getMethodAnnotation($reflection_method, JwtAuth::class);
    }
}
