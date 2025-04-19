<?php

namespace Tourze\JsonRPCTurboBundle\Service;

use Carbon\Carbon;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tourze\JsonRPC\Core\Contracts\RequestHandlerInterface;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Event\MethodExecuteFailureEvent;
use Tourze\JsonRPC\Core\Event\MethodExecuteSuccessEvent;
use Tourze\JsonRPC\Core\Exception\JsonRpcInvalidParamsException;
use Tourze\JsonRPC\Core\Exception\JsonRpcMethodNotFoundException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Model\JsonRpcResponse;

/**
 * Class JsonRpcRequestHandler
 */
#[AsAlias(RequestHandlerInterface::class)]
class JsonRpcRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly MethodResolver $methodResolver,
        private readonly ResponseCreator $responseCreator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly JsonRpcParamsValidator $methodParamsValidator,
    ) {
    }

    /**
     * @throws JsonRpcInvalidParamsException
     * @throws JsonRpcMethodNotFoundException
     */
    public function processJsonRpcRequest(JsonRpcRequest $request): JsonRpcResponse
    {
        $method = $this->resolveMethod($request);

        $this->validateParamList($request, $method);

        $startTime = Carbon::now();
        try {
            $result = $method->__invoke($request);
            $endTime = Carbon::now();

            $event = new MethodExecuteSuccessEvent();
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setResult($result);
            $event->setMethod($method);
            $event->setJsonRpcRequest($request);
            $this->eventDispatcher->dispatch($event);
        } catch (\Throwable $exception) {
            $endTime = Carbon::now();

            $event = new MethodExecuteFailureEvent();
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setException($exception);
            $event->setMethod($method);
            $event->setJsonRpcRequest($request);
            $this->eventDispatcher->dispatch($event);
        }

        return $this->createResponse($event);
    }

    /**
     * @throws JsonRpcMethodNotFoundException
     */
    public function resolveMethod(JsonRpcRequest $request): JsonRpcMethodInterface
    {
        $method = $this->methodResolver->resolve($request->getMethod());

        if (!$method instanceof JsonRpcMethodInterface) {
            throw new JsonRpcMethodNotFoundException($request->getMethod(), [
                'class' => $method ? $method::class : null,
            ]);
        }

        return $method;
    }

    /**
     * @throws JsonRpcInvalidParamsException
     */
    private function validateParamList(JsonRpcRequest $jsonRpcRequest, JsonRpcMethodInterface $method): void
    {
        if (null !== $this->methodParamsValidator) {
            $violationList = $this->methodParamsValidator->validate($jsonRpcRequest, $method);

            if ([] !== $violationList) {
                throw new JsonRpcInvalidParamsException($violationList);
            }
        }
    }

    protected function createResponse(MethodExecuteSuccessEvent|MethodExecuteFailureEvent $event): JsonRpcResponse
    {
        if ($event instanceof MethodExecuteSuccessEvent) {
            return $this->responseCreator->createResultResponse($event->getResult(), $event->getJsonRpcRequest());
        }

        /* @var $event MethodExecuteFailureEvent */
        return $this->responseCreator->createErrorResponse(
            $event->getException(),
            $event->getJsonRpcRequest()
        );
    }
}
