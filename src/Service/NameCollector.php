<?php

namespace Tourze\JsonRPCTurboBundle\Service;

/**
 * 记录所有可能的方法
 */
class NameCollector
{
    private array $procedures = [];

    public function getProcedures(): array
    {
        return $this->procedures;
    }

    public function addProcedure(string $method, string $className): void
    {
        $this->procedures[$method] = $className;
    }
}
