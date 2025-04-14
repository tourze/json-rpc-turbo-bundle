<?php

namespace Tourze\JsonRPCTurboBundle\Serialization;

use Tourze\JsonRPC\Core\Exception\JsonRpcInvalidRequestException;
use Tourze\JsonRPC\Core\Exception\JsonRpcParseErrorException;
use Tourze\JsonRPC\Core\Model\JsonRpcCallRequest;
use Tourze\JsonRPC\Core\Model\JsonRpcCallResponse;

/**
 * Class JsonRpcCallSerializer
 */
class JsonRpcCallSerializer
{
    public function __construct(
        private readonly JsonRpcCallDenormalizer $callDenormalizer,
        private readonly JsonRpcCallResponseNormalizer $callResponseNormalizer
    ) {
    }

    /**
     * @throws JsonRpcInvalidRequestException
     * @throws JsonRpcParseErrorException
     * @throws \Exception
     */
    public function deserialize(string $content): JsonRpcCallRequest
    {
        return $this->denormalize(
            $this->decode($content)
        );
    }

    public function serialize(JsonRpcCallResponse $jsonRpcCallResponse): string
    {
        return $this->encode(
            $this->normalize($jsonRpcCallResponse)
        );
    }

    /**
     * @param mixed $normalizedContent Could be an array or null for instance
     */
    public function encode(mixed $normalizedContent): string
    {
        return json_encode($normalizedContent, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array Decoded content
     * @throws JsonRpcParseErrorException
     * @throws JsonRpcInvalidRequestException
     */
    public function decode(string $requestContent): array
    {
        $decodedContent = \json_decode($requestContent, true);

        // Check if parsing is ok => Parse error
        if (\JSON_ERROR_NONE !== \json_last_error()) {
            throw new JsonRpcParseErrorException($requestContent, \json_last_error(), json_last_error_msg());
        }

        // Content must be either an array (normal request) or an array of array (batch request)
        //  => so must be an array
        // In case it's a batch call, at least one sub request must exist
        // and in case not, some required properties must exist
        // => array must have at least one child
        if (!is_array($decodedContent) || [] === $decodedContent) {
            throw new JsonRpcInvalidRequestException($requestContent);
        }

        return $decodedContent;
    }

    /**
     * @throws \Exception
     */
    public function denormalize(array $decodedContent): JsonRpcCallRequest
    {
        return $this->callDenormalizer->denormalize($decodedContent);
    }

    public function normalize(JsonRpcCallResponse $jsonRpcCallResponse): ?array
    {
        return $this->callResponseNormalizer->normalize($jsonRpcCallResponse);
    }
}
