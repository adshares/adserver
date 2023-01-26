<?php

namespace Adshares\Adserver\Http\Resources;

use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\ViewModel\CampaignStatus;
use Adshares\Adserver\ViewModel\ClickConversionType;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Ramsey\Uuid\Uuid;

class CampaignResource extends JsonResource
{
    private const CLICKS_RESOLUTION = 1e11;

    public function toArray($request): array
    {
        /** @var Campaign $this */
        return [
            'id' => Uuid::fromString($this->uuid)->toString(),
            'createdAt' => $this->created_at->format(DateTimeInterface::ATOM),
            'updatedAt' => $this->updated_at->format(DateTimeInterface::ATOM),
            'secret' => $this->secret,
            'conversionClick' => ClickConversionType::from($this->conversion_click)->toString(),
            'classifications' => BannerClassification::fetchCampaignClassifications($this->id),
            'conversionClickLink' => $this->conversion_click_link,
            'targeting' => $this->targeting,
            'creatives' => BannerResource::collection($this->ads),
            'bidStrategyUuid' => $this->bid_strategy_uuid,
            'conversions' => $this->conversions,
            'status' => CampaignStatus::from($this->status)->toString(),
            'name' => $this->name,
            'targetUrl' => $this->landing_url,
            'maxCpc' => null === $this->max_cpc ? null : $this->max_cpc / self::CLICKS_RESOLUTION,
            'maxCpm' => null === $this->max_cpm ? null : $this->max_cpm / self::CLICKS_RESOLUTION,
            'budget' => $this->budget / self::CLICKS_RESOLUTION,
            'medium' => $this->medium,
            'vendor' => $this->vendor,
            'dateStart' => $this->time_start,
            'dateEnd' => $this->time_end,
        ];
    }
}
