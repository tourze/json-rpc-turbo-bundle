<?php

namespace Tourze\JsonRPCTurboBundle\Serialization;

use Tourze\JsonRPC\Core\Model\JsonRpcCallResponse;

class JsonRpcCallResponseNormalizer
{
    public function __construct(private readonly JsonRpcResponseNormalizer $responseNormalizer)
    {
    }

    public function normalize(JsonRpcCallResponse $jsonRpcCallResponse): ?array
    {
        $resultList = [];
        foreach ($jsonRpcCallResponse->getResponseList() as $response) {
            // Notifications must not have a response, even if they are on error
            if (!$response->isNotification()) {
                $resultList[] = $this->responseNormalizer->normalize($response);
            }
        }

        // if no result, it means It was either :
        // - a batch call with only notifications
        // - a notification request
        // => return null response in all cases
        if ([] === $resultList) {
            return null;
        }

        // In case it's not a batch, return the first (lonely) result
        if (!$jsonRpcCallResponse->isBatch()) {
            return array_shift($resultList);
        }

        return $resultList;
    }
}
