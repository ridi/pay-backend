<?php
declare(strict_types=1);

namespace RidiPay\Library\Cors;

use Doctrine\Common\Annotations\CachedReader;
use RidiPay\Kernel;
use RidiPay\Library\Cors\Annotation\Cors;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsMiddleware implements EventSubscriberInterface
{
    private const ACCESS_CONTROL_ALLOW_HEADERS = ['Content-Type'];

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
            KernelEvents::RESPONSE => 'onKernelResponse',
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

        if (!$event->isMasterRequest()) {
            return;
        }

        $event->getRequest()->attributes->set('access_control_allow_methods', $annotation->getMethods());

        return;
    }

    /**
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $access_control_allow_methods = $request->attributes->get('access_control_allow_methods');
        if (empty($access_control_allow_methods)) {
            return;
        }

        $origin = $request->headers->get('Origin');
        if ($origin === null) {
            return;
        }

        if (Kernel::isDev()) {
            $regex_sub_domains_of_ridi_io = '/^https:\/\/[a-zA-Z0-9-_\.]+\.ridi\.io$/';
            if (!preg_match($regex_sub_domains_of_ridi_io, $origin)) {
                return;
            }
        } else {
            if (!in_array($origin, self::getAccessControlAllowOrigins(), true)) {
                return;
            }
        }

        $response = $event->getResponse();
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        if ($request->getRealMethod() === Request::METHOD_OPTIONS) {
            $response->headers->set('Access-Control-Allow-Methods', implode(', ', $access_control_allow_methods));
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', self::ACCESS_CONTROL_ALLOW_HEADERS));
        }
    }

    /**
     * @param mixed $controller
     * @param string $method_name
     * @return null|Cors
     * @throws \ReflectionException
     */
    private function getAnnotation($controller, string $method_name): ?Cors
    {
        $reflection_class = new \ReflectionClass($controller);
        $reflection_method = $reflection_class->getMethod($method_name);

        return $this->annotation_reader->getMethodAnnotation($reflection_method, Cors::class);
    }

    /**
     * @return array
     */
    private static function getAccessControlAllowOrigins(): array
    {
        return [
            getenv('RIDI_PAY_URL', true),
            getenv('RIDIBOOKS_SERVER_HOST', true),
            getenv('RIDISELECT_URL', true)
        ];
    }
}
