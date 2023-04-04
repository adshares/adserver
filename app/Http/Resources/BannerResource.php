<?php

namespace Adshares\Adserver\Http\Resources;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\ViewModel\BannerStatus;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Ramsey\Uuid\Uuid;

class BannerResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Banner $this */
        return [
            'id' => Uuid::fromString($this->uuid)->toString(),
            'createdAt' => $this->created_at->format(DateTimeInterface::ATOM),
            'updatedAt' => $this->updated_at->format(DateTimeInterface::ATOM),
            'type' => $this->creative_type,
            'mime' => $this->creative_mime,
            'hash' => $this->creative_sha1,
            'scope' => $this->creative_size,
            'name' => $this->name,
            'status' => BannerStatus::from($this->status)->toString(),
            'cdnUrl' => $this->cdn_url,
            'url' => $this->url,
        ];
    }
}
