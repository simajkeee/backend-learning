<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\ExceptionMapper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

readonly class ExceptionListener
{
    public function __construct(private ExceptionMapper $mapper)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $httpResponse = $this->mapper->toHttpResponse(
            $event->getThrowable()
        );

        if ($httpResponse) {
            $response = new Response();
            $response->setStatusCode($httpResponse->getCode());
            $response->setContent($httpResponse->getError());
            $event->setResponse($response);
        }
    }
}
