<?php

namespace Tourze\JsonRPCTurboBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BacktraceHelper\Backtrace;
use Tourze\JsonRPCTurboBundle\Service\JsonRpcRequestHandler;

class JsonRPCTurboBundle extends Bundle
{
    public function boot(): void
    {
        parent::boot();
        Backtrace::addProdIgnoreFiles((new \ReflectionClass(JsonRpcRequestHandler::class))->getFileName());
    }
}
