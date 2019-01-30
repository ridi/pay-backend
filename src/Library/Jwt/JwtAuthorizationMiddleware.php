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
        if (!$this->isJwtAuthAnnotated($controller, $method_name)) {
            return;
        }

        try {
            self::authorize($event->getRequest());
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
     * @throws \Exception
     */
    private static function authorize(Request $request)
    {
        $authorization_header = $request->headers->get('Authorization');
        if (is_null($authorization_header)) {
            throw new \Exception("Authorization header doesn't exist");
        }

        $jwt = sscanf($authorization_header, 'Bearer %s')[0];
        if (is_null($jwt)) {
            throw new \Exception('Invalid authorization header');
        }

        JwtAuthorizationHelper::decodeJwt($jwt, JwtAuthorizationServiceNameConstant::RIDI_PAY);
    }

    /**
     * @param mixed $controller
     * @param string $method_name
     * @return bool
     * @throws \ReflectionException
     */
    private function isJwtAuthAnnotated($controller, string $method_name): bool
    {
        return $this->isJwtAuthAnnotatedOnClass($controller)
            || $this->isJwtAuthAnnotatedOnMethod($controller, $method_name);
    }

    /**
     * @param mixed $controller
     * @return bool
     * @throws \ReflectionException
     */
    private function isJwtAuthAnnotatedOnClass($controller): bool
    {
        $reflection_class = new \ReflectionClass($controller);

        return !is_null($this->annotation_reader->getClassAnnotation($reflection_class, JwtAuth::class));
    }

    /**
     * @param mixed $controller
     * @param string $method_name
     * @return bool
     * @throws \ReflectionException
     */
    private function isJwtAuthAnnotatedOnMethod($controller, string $method_name): bool
    {
        $reflection_class = new \ReflectionClass($controller);
        $reflection_method = $reflection_class->getMethod($method_name);

        return !is_null($this->annotation_reader->getMethodAnnotation($reflection_method, JwtAuth::class));
    }
}
