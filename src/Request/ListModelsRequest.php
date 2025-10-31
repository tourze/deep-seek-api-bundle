<?php

namespace Tourze\DeepSeekApiBundle\Request;

use HttpClientBundle\Request\ApiRequest;

class ListModelsRequest extends ApiRequest
{
    public function getRequestPath(): string
    {
        return '/models';
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
