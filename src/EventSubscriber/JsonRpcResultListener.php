<?php

namespace Tourze\JsonRPCTurboBundle\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Service\ResetInterface;
use Tourze\JsonRPCTurboBundle\Traits\AppendJsonRpcResultAware;
use Tourze\UserEventBundle\Event\UserInteractionEvent;

/**
 * @deprecated 这个设计不好，如果需要自定义数据的话，在业务内抛出来算了
 */
#[AutoconfigureTag('as-coroutine')]
class JsonRpcResultListener implements ResetInterface
{
    private array $result = [];

    public function __invoke(UserInteractionEvent $event): void
    {
        if (!in_array(AppendJsonRpcResultAware::class, class_uses($event))) {
            return;
        }

        /* @var AppendJsonRpcResultAware $event */
        $this->result = array_merge($this->result, $event->getJsonRpcResult());
    }

    public function reset(): void
    {
        $this->result = [];
    }

    public function getResult(): array
    {
        return $this->result;
    }

    public function setResult(array $result): void
    {
        $this->result = $result;
    }
}
