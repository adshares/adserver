<?php

namespace Adshares\Adserver\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BannerCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return $this->resource->toArray();
    }

    public function paginationInformation(): array
    {
        return [];
    }
}