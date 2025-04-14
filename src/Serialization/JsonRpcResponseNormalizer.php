<?php

namespace Tourze\JsonRPCTurboBundle\Serialization;

use Tourze\JsonRPC\Core\Exception\JsonRpcExceptionInterface;
use Tourze\JsonRPC\Core\Model\JsonRpcResponse;

/**
 * Class JsonRpcResponseNormalizer
 */
class JsonRpcResponseNormalizer
{
    final public const KEY_JSON_RPC = 'jsonrpc';

    final public const KEY_ID = 'id';

    final public const KEY_RESULT = 'result';

    final public const KEY_ERROR = 'error';

    final public const SUB_KEY_ERROR_CODE = 'code';

    final public const SUB_KEY_ERROR_MESSAGE = 'message';

    final public const SUB_KEY_ERROR_DATA = 'data';

    public function normalize(JsonRpcResponse $response): ?array
    {
        // Notifications must not have a response, even if they are on error
        if ($response->isNotification()) {
            return null;
        }

        $data = [
            self::KEY_JSON_RPC => $response->getJsonrpc(),
            self::KEY_ID => $response->getId(),
        ];

        if ($response->getError()) {
            $data[self::KEY_ERROR] = $this->normalizeError(
                $response->getError()
            );
        } else {
            $data[self::KEY_RESULT] = $response->getResult();
        }

        return $data;
    }

    private function normalizeError(JsonRpcExceptionInterface $error): array
    {
        $normalizedError = [
            self::SUB_KEY_ERROR_CODE => $error->getErrorCode(),
            self::SUB_KEY_ERROR_MESSAGE => $error->getErrorMessage(),
        ];

        if ($error->getErrorData()) {
            $normalizedError[self::SUB_KEY_ERROR_DATA] = $error->getErrorData();
        }

        return $normalizedError;
    }
}
