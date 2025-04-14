<?php

namespace Tourze\JsonRPCTurboBundle\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodResolverInterface;

/**
 * Class MethodResolver
 */
class MethodResolver implements JsonRpcMethodResolverInterface
{
    public function __construct(
        #[Autowire(service: 'json_rpc_http_server.service_locator.method_resolver')] private readonly ContainerInterface $locator
    ) {
    }

    public function resolve(string $methodName): ?JsonRpcMethodInterface
    {
        // 兼容特殊情况
        if (isset($_ENV["JSON_RPC_METHOD_REMAP_{$methodName}"])) {
            $methodName = $_ENV["JSON_RPC_METHOD_REMAP_{$methodName}"];
        }

        return $this->locator->has($methodName)
            ? $this->locator->get($methodName)
            : null;
    }

    public function getAllMethodNames(): array
    {
        // 获取所有已定义的条目名称
        return array_keys($this->locator->getProvidedServices());
    }
}
