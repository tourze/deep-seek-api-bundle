<?php

namespace Tourze\DeepSeekApiBundle\Request;

use HttpClientBundle\Request\ApiRequest;

class GetBalanceRequest extends ApiRequest
{
    public function getRequestPath(): string
    {
        return '/user/balance';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRequestOptions(): ?array
    {
        return null;
    }

    public function getRequestMethod(): ?string
    {
        return 'GET';
    }
}
