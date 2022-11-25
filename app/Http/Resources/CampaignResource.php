<?php

namespace Adshares\Adserver\Http\Resources;

use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\Campaign;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Campaign $this */
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'createdAt' => $this->created_at->format(DateTimeInterface::ATOM),
            'updatedAt' => $this->updated_at->format(DateTimeInterface::ATOM),
            'secret' => $this->secret,
            'conversionClick' => $this->conversion_click,
            'classifications' => BannerClassification::fetchCampaignClassifications($this->id),
            'conversionClickLink' => $this->conversion_click_link,
            'targeting' => $this->targeting,
            'ads' => $this->ads,
            'bidStrategy' => $this->bid_strategy,
            'conversions' => $this->conversions,
            ...$this->basic_information,
        ];
    }
}
