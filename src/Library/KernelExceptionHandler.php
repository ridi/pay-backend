<?php
declare(strict_types=1);

namespace RidiPay\Library;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelExceptionHandler implements EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    /**
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event): void
    {
        $exception = $event->getException();

        if ($exception instanceof HttpExceptionInterface) {
            $response = new JsonResponse(
                [
                    'code' => $exception->getStatusCode(),
                    'message' => Response::$statusTexts[$exception->getStatusCode()]
                ],
                $exception->getStatusCode()
            );
            $response->headers->replace($exception->getHeaders());
        } else {
            SentryHelper::captureException($exception);

            $response = new JsonResponse(
                [
                    'code' => 'INTERNAL_SERVER_ERROR',
                    'message' => Response::$statusTexts[Response::HTTP_INTERNAL_SERVER_ERROR]
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $event->setResponse($response);
    }
}
