<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Jobs;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Notification;
use Adshares\Common\Application\Service\AdClassify;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ClassifyCampaign implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const QUEUE_NAME = 'service_classify';

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

    public function handle(AdClassify $adclassify)
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
