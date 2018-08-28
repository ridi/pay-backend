<?php
declare(strict_types=1);

namespace RidiPay\Library\Jwt;

use Doctrine\Common\Annotations\CachedReader;
use RidiPay\Library\Jwt\Annotation\Jwt;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class JwtMiddleware implements EventSubscriberInterface
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
     */
    public function onKernelController(FilterControllerEvent $event): void
    {
        if (!is_array($event->getController())) {
            return;
        }

        [$controller, $method_name] = $event->getController();
        if (!$this->isJwtAnnotated($controller, $method_name)) {
            return;
        }

        try {
            self::authorize($event->getRequest());
        } catch (\Exception $e) {
            $event->setController(function () use ($e) {
                return new JsonResponse(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
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
        $jwt = sscanf($authorization_header, 'Bearer %s')[0];

        if (is_null($jwt)) {
            throw new \Exception('Invalid authorization header');
        }

        JwtAuthorizationHelper::decodeJwt($jwt, JwtAuthorizationServiceNameConstant::RIDI_PAY);
    }

    /**
     * @param $controller
     * @param string $method_name
     * @return bool
     */
    private function isJwtAnnotated($controller, string $method_name): bool
    {
        return $this->isOauth2AnnotatedOnClass($controller)
            || $this->isOAuth2AnnotatedOnMethod($controller, $method_name);
    }

    /**
     * @param $controller
     * @return bool
     */
    private function isOauth2AnnotatedOnClass($controller): bool
    {
        $reflection_class = new \ReflectionClass($controller);

        return !is_null($this->annotation_reader->getClassAnnotation($reflection_class, Jwt::class));
    }

    /**
     * @param $controller
     * @param $method_name
     * @return bool
     */
    private function isOAuth2AnnotatedOnMethod($controller, string $method_name): bool
    {
        $reflectionObject = new \ReflectionObject($controller);
        $reflectionMethod = $reflectionObject->getMethod($method_name);

        return !is_null($this->annotation_reader->getMethodAnnotation($reflectionMethod, Jwt::class));
    }
}
