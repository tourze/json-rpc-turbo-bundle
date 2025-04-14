<?php

namespace Tourze\JsonRPCTurboBundle\Serialization;

use Tourze\JsonRPC\Core\Exception\JsonRpcInvalidRequestException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;

/**
 * Class JsonRpcRequestDenormalizer
 */
class JsonRpcRequestDenormalizer
{
    final public const KEY_JSON_RPC = 'jsonrpc';

    final public const KEY_ID = 'id';

    final public const KEY_METHOD = 'method';

    final public const KEY_PARAM_LIST = 'params';

    /**
     * @param mixed $item Should be an array
     * @throws JsonRpcInvalidRequestException
     */
    public function denormalize(mixed $item): JsonRpcRequest
    {
        $this->validateArray($item, 'Item must be an array');

        // Validate json-rpc and method keys
        $this->validateRequiredKey($item, self::KEY_JSON_RPC);
        $this->validateRequiredKey($item, self::KEY_METHOD);

        $request = new JsonRpcRequest();
        $request->setJsonrpc($item[self::KEY_JSON_RPC]);
        $request->setMethod($item[self::KEY_METHOD]);

        $this->bindIdIfProvided($request, $item);
        $this->bindParamListIfProvided($request, $item);

        return $request;
    }

    protected function bindIdIfProvided(JsonRpcRequest $request, array $item): void
    {
        /* If no id defined => request is a notification */
        if (isset($item[self::KEY_ID])) {
            $request->setId(
                $item[self::KEY_ID] == (string) ((int) $item[self::KEY_ID])
                    ? (int) $item[self::KEY_ID] // Convert it in case it's a string containing an int
                    : (string) $item[self::KEY_ID] // Convert to string in all other cases
            );
        }
    }

    /**
     * @throws JsonRpcInvalidRequestException
     */
    protected function bindParamListIfProvided(JsonRpcRequest $request, array $item): void
    {
        $params = new JsonRpcParams();
        if (isset($item[self::KEY_PARAM_LIST])) {
            $this->validateArray($item[self::KEY_PARAM_LIST], 'Parameter list must be an array');
            $params->replace($item[self::KEY_PARAM_LIST]);
        }
        $request->setParams($params);
    }

    /**
     * @throws JsonRpcInvalidRequestException
     */
    private function validateArray(mixed $value, string $errorDescription): void
    {
        if (!is_array($value)) {
            throw new JsonRpcInvalidRequestException($value, $errorDescription);
        }
    }

    /**
     * @throws JsonRpcInvalidRequestException
     */
    private function validateRequiredKey(array $item, string $key): void
    {
        if (!isset($item[$key])) {
            throw new JsonRpcInvalidRequestException(
                $item,
                sprintf('"%s" is a required key', $key)
            );
        }
    }
}
