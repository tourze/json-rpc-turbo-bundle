<?php

namespace Tourze\JsonRPCTurboBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodInterface;
use Tourze\JsonRPC\Core\Domain\JsonRpcMethodParamsValidatorInterface;
use Tourze\JsonRPC\Core\Domain\MethodWithValidatedParamsInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;

/**
 * Class JsonRpcParamsValidator
 */
class JsonRpcParamsValidator implements JsonRpcMethodParamsValidatorInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $procedureLogger
    ) {
    }

    public function validate(JsonRpcRequest $jsonRpcRequest, JsonRpcMethodInterface $method): array
    {
        $violationList = [];
        if (!$method instanceof MethodWithValidatedParamsInterface) {
            return $violationList;
        }

        $value = $jsonRpcRequest->getParams()->toArray();
        $constraints = $method->getParamsConstraint();
        $this->procedureLogger->debug('进行JsonRPC请求参数校验', [
            'value' => $value,
            'constraints' => $constraints,
        ]);
        $sfViolationList = $this->validator->validate($value, $constraints);

        foreach ($sfViolationList as $violation) {
            /* @var ConstraintViolationInterface $violation */
            $violationList[] = [
                'path' => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
                'code' => $violation->getCode(),
            ];
        }

        return $violationList;
    }
}
