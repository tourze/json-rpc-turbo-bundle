<?php

namespace Tourze\JsonRPCTurboBundle\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\JsonRPC\Core\Event\OnExceptionEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Model\JsonRpcResponse;

/**
 * Class ExceptionHandler
 */
class ExceptionHandler
{
    public function __construct(
        private readonly ResponseCreator $responseCreator,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getJsonRpcResponseFromException(
        \Throwable $exception,
        ?JsonRpcRequest $fromRequest = null
    ): JsonRpcResponse {
        $event = new OnExceptionEvent();
        $event->setException($exception);
        $event->setFromJsonRpcRequest($fromRequest);
        $this->eventDispatcher->dispatch($event);

        return $this->responseCreator->createErrorResponse($event->getException(), $fromRequest);
    }
}
