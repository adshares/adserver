<?php

namespace Adshares\Adserver\Http\Resources;

use Adshares\Adserver\ViewModel\BannerStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource->toArray();
        unset($data['campaign']);
        $data['status'] = BannerStatus::from($data['status'])->toString();
        return $data;
    }
}
