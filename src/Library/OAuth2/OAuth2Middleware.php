<?php
declare(strict_types=1);

namespace RidiPay\Library\OAuth2;

use Doctrine\Common\Annotations\CachedReader;
use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;
use RidiPay\Library\OAuth2\Annotation\OAuth2;
use RidiPay\Library\OAuth2\Handler\LoginRequiredExceptionHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class OAuth2Middleware implements EventSubscriberInterface
{
    /** @var OAuth2Manager */
    private $oauth2_manager;

    /** @var CachedReader */
    private $annotation_reader;

    /**
     * @param OAuth2Manager $oauth2_manager
     * @param CachedReader $reader
     */
    public function __construct(OAuth2Manager $oauth2_manager, CachedReader $reader)
    {
        $this->oauth2_manager = $oauth2_manager;
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
        [$controller, $method_name] = $event->getController();
        if (!$this->isOAuth2Annotated($controller, $method_name)) {
            return;
        }

        try {
            $this->authorize($event->getRequest());
        } catch (AuthorizationException $e) {
            $event->setController(function () use ($e, $event) {
                $exception_handler = new LoginRequiredExceptionHandler();
                return $exception_handler->handle($e, $event->getRequest());
            });
        }

        return;
    }

    /**
     * @param Request $request
     * @throws AuthorizationException
     */
    public function authorize(Request $request)
    {
        $token = $this->oauth2_manager->getAuthorizer()->authorize($request);
        $this->oauth2_manager->loadUser($token, $request);
    }

    /**
     * @param $controller
     * @param string $method_name
     * @return bool
     */
    private function isOAuth2Annotated($controller, string $method_name): bool
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

        return !is_null($this->annotation_reader->getClassAnnotation($reflection_class, OAuth2::class));
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

        return !is_null($this->annotation_reader->getMethodAnnotation($reflectionMethod, OAuth2::class));
    }
}
