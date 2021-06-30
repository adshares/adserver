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

declare(strict_types=1);

namespace Adshares\Adserver\Client\Mapper\AdSelect;

use Adshares\Adserver\Client\Mapper\JsonValueMapper;
use Adshares\Adserver\Models\NetworkCase;
use DateTimeInterface;

class CaseMapper
{
    public static function map(NetworkCase $caseWithImpression): array
    {
        return [
            'id' => $caseWithImpression->id,
            'created_at' => $caseWithImpression->created_at->format(DateTimeInterface::ATOM),
            'publisher_id' => $caseWithImpression->publisher_id,
            'site_id' => $caseWithImpression->site_id,
            'zone_id' => $caseWithImpression->zone_id,
            'campaign_id' => $caseWithImpression->campaign_id,
            'banner_id' => $caseWithImpression->banner_id,
            'impression_id' => $caseWithImpression->impression_id,
            'tracking_id' => $caseWithImpression->tracking_id,
            'user_id' => $caseWithImpression->user_id ?? $caseWithImpression->tracking_id,
            'human_score' => null !== $caseWithImpression->human_score ? (float)$caseWithImpression->human_score : null,
            'page_rank' => null !== $caseWithImpression->page_rank ? (float)$caseWithImpression->page_rank : null,
            'keywords' => JsonValueMapper::map($caseWithImpression->user_data),
        ];
    }
}
