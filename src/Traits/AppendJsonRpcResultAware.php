<?php

namespace Tourze\JsonRPCTurboBundle\Traits;

/**
 * @deprecated 这个设计不好，如果需要自定义数据的话，在业务内抛出来算了
 */
trait AppendJsonRpcResultAware
{
    private array $jsonRpcResult = [];

    public function getJsonRpcResult(): array
    {
        return $this->jsonRpcResult;
    }

    public function setJsonRpcResult(array $jsonRpcResult): void
    {
        $this->jsonRpcResult = $jsonRpcResult;
    }
}
