<?php

namespace Adshares\Adserver\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class GenericCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return array_filter(
            $this->resource->toArray(),
            fn($key) => in_array($key, ['data', 'links', 'meta'], true),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
