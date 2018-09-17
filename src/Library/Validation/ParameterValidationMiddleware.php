<?php
declare(strict_types=1);

namespace RidiPay\Library\Validation;

use Doctrine\Common\Annotations\CachedReader;
use RidiPay\Controller\Response\CommonErrorCodeConstant;
use RidiPay\Library\Validation\Annotation\ParamValidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ParameterValidationMiddleware implements EventSubscriberInterface
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
            $parameters = json_decode($event->getRequest()->getContent(), true);
            ParameterValidator::validate($parameters, $annotation->getRules());
        } catch (ParameterValidationException $e) {
            $event->setController(function () use ($e) {
                $message = $e->getMessage();
                $parameter = $e->getParameter();
                return new JsonResponse(
                    [
                        'code' => 'INVALID_PARAMETER',
                        'message' => "{$parameter}: {$message}"
                    ],
                    Response::HTTP_BAD_REQUEST
                );
            });
        }

        return;
    }

    /**
     * @param $controller
     * @param string $method_name
     * @return null|ParamValidator
     * @throws \ReflectionException
     */
    private function getAnnotation($controller, string $method_name): ?ParamValidator
    {
        $reflection_class = new \ReflectionClass($controller);
        $reflection_method = $reflection_class->getMethod($method_name);

        return $this->annotation_reader->getMethodAnnotation($reflection_method, ParamValidator::class);
    }
}
