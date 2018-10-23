<?php

namespace Adshares\Adserver\Jobs;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Notification;
use Adshares\Adserver\Services\Adclassify;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ClassifyCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const QUEUE_NAME = 'service_classify';

    private $campaignId;
    private $targetingRequires;
    private $targetingExcludes;
    private $banners;

    public function __construct(int $campaignId, ?array $targetingRequires, ?array $targetingExcludes, ?array $banners)
    {
        $this->campaignId = $campaignId;
        $this->targetingRequires = $targetingRequires;
        $this->targetingExcludes = $targetingExcludes;
        $this->banners = $banners;
        $this->queue = self::QUEUE_NAME;
    }

    public function handle(Adclassify $adclassify)
    {
        $tags = $adclassify->send(
            $this->campaignId,
            $this->targetingRequires,
            $this->targetingExcludes,
            $this->banners
        );

        $campaign = Campaign::campaignById($this->campaignId);

        $campaign->classification_tags = implode(',', $tags);
        $campaign->classification_status = 2;

        $campaign->update();

        $message = sprintf("Campaign Id: %s, Tags: %s", $campaign->id, $campaign->classification_tags);
        Notification::add(
            $campaign->user_id,
            Notification::CLASSIFICATION_TYPE,
            'Campaign has been classified',
            $message
        );
    }
}
