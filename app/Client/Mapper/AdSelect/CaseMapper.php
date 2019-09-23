<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Client\Mapper\AdSelect;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use Adshares\Adserver\Models\NetworkCase;
use DateTime;
use stdClass;
use function is_object;
use function property_exists;

class CaseMapper
{
    public static function map(NetworkCase $caseWithImpression): array
    {
        $keywords = self::getNormalizedKeywords($caseWithImpression);
        if (!$keywords) {
            $keywords = new stdClass();
        }

        return [
            'id' => $caseWithImpression->id,
            'created_at' => $caseWithImpression->created_at->format(DateTime::ATOM),
            'publisher_id' => $caseWithImpression->publisher_id,
            'zone_id' => $caseWithImpression->zone_id,
            'campaign_id' => $caseWithImpression->campaign_id,
            'banner_id' => $caseWithImpression->banner_id,
            'impression_id' => $caseWithImpression->impression_id,
            'tracking_id' => $caseWithImpression->tracking_id,
            'user_id' => $caseWithImpression->user_id,
            'keywords' => $keywords,
        ];
    }

    private static function getNormalizedKeywords(NetworkCase $caseWithImpression): ?array
    {
        $keywords = null;

        $context = $caseWithImpression->context;
        if (is_object($context)
            && property_exists($context, 'site')
            && is_object($site = $context->site)
            && property_exists($site, 'keywords')) {
            $keywords = AbstractFilterMapper::generateNestedStructure(['site' => (array)$site->keywords]);
        }

        return $keywords;
    }
}
