<?php

namespace Tourze\JsonRPCTurboBundle\Service;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use Tourze\JsonRPC\Core\Contracts\EndpointInterface;
use Tourze\JsonRPC\Core\Event\BatchSubRequestProcessedEvent;
use Tourze\JsonRPC\Core\Event\MethodExecutingEvent;
use Tourze\JsonRPC\Core\Event\OnBatchSubRequestProcessingEvent;
use Tourze\JsonRPC\Core\Event\RequestStartEvent;
use Tourze\JsonRPC\Core\Event\ResponseSendingEvent;
use Tourze\JsonRPC\Core\Model\JsonRpcCallRequest;
use Tourze\JsonRPC\Core\Model\JsonRpcCallResponse;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Model\JsonRpcResponse;
use Tourze\JsonRPCTurboBundle\EventSubscriber\JsonRpcResultListener;
use Tourze\JsonRPCTurboBundle\Serialization\JsonRpcCallSerializer;

class JsonRpcEndpoint implements ResetInterface, EndpointInterface
{
    private Stopwatch $stopwatch;

    public function __construct(
        private readonly JsonRpcCallSerializer $jsonRpcCallSerializer,
        private readonly JsonRpcRequestHandler $jsonRpcRequestHandler,
        private readonly ExceptionHandler $exceptionHandler,
        private readonly LoggerInterface $procedureLogger,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly JsonRpcResultListener $jsonRpcResultListener,
        private readonly CacheInterface $cache,
    ) {
        $this->stopwatch = new Stopwatch(true);
    }

    public function index(string $payload, ?Request $request = null): string
    {
        $event = new RequestStartEvent();
        $event->setPayload($payload);
        $event->setRequest($request);
        $this->eventDispatcher->dispatch($event);
        $payload = $event->getPayload();

        if (empty($payload) || !json_validate($payload)) {
            $this->procedureLogger->error('JsonRPC请求时，传入了无效的字符串', [
                'request' => $payload,
            ]);

            return '';
        }

        $this->procedureLogger->debug('JsonRPC接收到请求', [
            'request' => $payload,
        ]);
        $jsonRpcCall = null;
        try {
            $e = $this->stopwatch->start($payload, '执行JsonRPC请求');
            $jsonRpcCall = $this->jsonRpcCallSerializer->deserialize($payload);
            $jsonRpcCallResponse = $this->getJsonRpcCallResponse($jsonRpcCall, $payload);
            $e->stop();
            $resp = $this->getResponseString($jsonRpcCallResponse, $jsonRpcCall);
            $this->procedureLogger->debug('执行JsonRPC请求结束', [
                'duration' => $e->getDuration(),
                'memory' => $e->getMemory(),
            ]);
        } catch (\Throwable $exception) {
            // Try to create a valid json-rpc error
            $jsonRpcCallResponse = (new JsonRpcCallResponse())->addResponse(
                $this->exceptionHandler->getJsonRpcResponseFromException($exception)
            );

            $resp = $this->getResponseString($jsonRpcCallResponse, $jsonRpcCall);
        } finally {
            $this->stopwatch->reset();
        }

        $event = new ResponseSendingEvent();
        $event->setRequest($request);
        $event->setResponseString($resp);
        $event->setJsonRpcCallResponse($jsonRpcCallResponse);
        $event->setJsonRpcCall($jsonRpcCall);
        $this->eventDispatcher->dispatch($event);

        return $event->getResponseString();
    }

    public function reset(): void
    {
        $this->stopwatch->reset();
    }

    protected function getResponseString(
        JsonRpcCallResponse $jsonRpcCallResponse,
        ?JsonRpcCallRequest $jsonRpcCall = null,
    ): string {
        foreach ($jsonRpcCallResponse->getResponseList() as $response) {
            $r = $response->getResult();
            $append = $this->jsonRpcResultListener->getResult();
            $this->procedureLogger->debug('补充额外的返回值', [
                'append' => $append,
                'result' => $r,
            ]);
            if (is_array($r) && !empty($append)) {
                $r = array_merge($r, $append);
                $response->setResult($r);
            }
        }
        $this->jsonRpcResultListener->setResult([]);

        return $this->jsonRpcCallSerializer->serialize($jsonRpcCallResponse);
    }

    protected function getJsonRpcCallResponse(JsonRpcCallRequest $jsonRpcCall, string $request): JsonRpcCallResponse
    {
        $jsonRpcCallResponse = new JsonRpcCallResponse($jsonRpcCall->isBatch());

        foreach ($jsonRpcCall->getItemList() as $itemPosition => $item) {
            if ($jsonRpcCall->isBatch()) {
                $event = new OnBatchSubRequestProcessingEvent();
                $event->setItemPosition($itemPosition);
                $this->eventDispatcher->dispatch($event);
            }

            $jsonRpcCallResponse->addResponse($this->processItem($item));
            if ($jsonRpcCall->isBatch()) {
                $event = new BatchSubRequestProcessedEvent();
                $event->setItemPosition($itemPosition);
                $this->eventDispatcher->dispatch($event);
            }
        }

        return $jsonRpcCallResponse;
    }

    private function processItem(JsonRpcRequest|\Exception $item): JsonRpcResponse
    {
        try {
            if ($item instanceof \Exception) {
                // Exception will be caught just below and converted to response
                throw $item;
            }

            if (isset($_ENV['JSONRPC_ID_REUSE_INTERVAL'])) {
                $cacheKey = 'jsonRpcId' . $item->getId();
                if ($this->cache->has($cacheKey)) {
                    throw new \RuntimeException('请求频繁，请稍后再试');
                }
                $this->cache->set($cacheKey, true, (int) $_ENV['JSONRPC_ID_REUSE_INTERVAL']);
            }

            $event = new MethodExecutingEvent();
            $event->setItem($item);
            $this->eventDispatcher->dispatch($event);

            if ($event->getResponse()) {
                return $event->getResponse();
            }

            return $this->jsonRpcRequestHandler->processJsonRpcRequest($item);
        } catch (\Throwable $exception) {
            return $this->exceptionHandler->getJsonRpcResponseFromException(
                $exception,
                $item instanceof JsonRpcRequest ? $item : null
            );
        }
    }
}
