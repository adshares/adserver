<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Http\Requests\Campaign;

use Adshares\Common\Application\Dto\TaxonomyV2\Medium;

class CampaignTargetingProcessor
{
    private TargetingProcessor $targetingProcessor;

    public function __construct(Medium $medium)
    {
        $this->targetingProcessor = new TargetingProcessor($medium);
    }

    public function processTargetingRequire(array $targeting): array
    {
        return $this->processTargeting($targeting, 'app.campaign_targeting_require');
    }

    public function processTargetingExclude(array $targeting): array
    {
        return $this->processTargeting($targeting, 'app.campaign_targeting_exclude');
    }

    private function processTargeting(array $targeting, string $configKey): array
    {
        $serverTargeting = json_decode(config($configKey) ?? '', true);
        if (is_array($serverTargeting)) {
            $targeting = array_map([__CLASS__, 'normalize'], array_merge_recursive($targeting, $serverTargeting));
        }

        return $this->targetingProcessor->processTargeting($targeting);
    }

    private static function normalize($arr)
    {
        if (!is_array($arr) || empty($arr)) {
            return $arr;
        }
        if (array_keys($arr) !== range(0, count($arr) - 1)) {
            return $arr;
        }
        return array_unique($arr);
    }
}
