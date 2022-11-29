<?php

namespace Adshares\Adserver\Http\Resources;

use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\ViewModel\CampaignStatus;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Campaign $this */
        $basicInformation = $this->basic_information;
        $basicInformation['status'] = strtolower(CampaignStatus::from($basicInformation['status'])->name);
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
            'ads' => new BannerCollection($this->ads),
            'bidStrategyUuid' => $this->bid_strategy_uuid,
            'conversions' => $this->conversions,
            ...$basicInformation,
        ];
    }
}
