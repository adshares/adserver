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

namespace Adshares\Adserver\Http\Requests;

use Adshares\Adserver\Services\Publisher\SiteCodeConfig;
use Adshares\Adserver\Services\Publisher\SiteCodeConfigPops;

class GetSiteCode extends FormRequest
{
    public function rules(): array
    {
        return [
            'is_proxy' => 'boolean',
            'is_block' => 'boolean',
            'is_fallback' => 'boolean',
            'min_cpm' => 'numeric|nullable',
            'fallback_rate' => 'numeric|nullable',
            'pop_count' => 'integer|required_with:pop_interval,pop_burst',
            'pop_interval' => 'integer|required_with:pop_count,pop_burst',
            'pop_burst' => 'integer|required_with:pop_count,pop_interval',
        ];
    }

    public function toConfig(): SiteCodeConfig
    {
        $values = $this->validated();

        $isProxy = $this->filterBoolean($values, 'is_proxy');
        $isBlock = $this->filterBoolean($values, 'is_block');
        $isFallback = $this->filterBoolean($values, 'is_fallback');
        $minCpm = $values['min_cpm'] ?? null;
        $fallbackRate = $values['fallback_rate'] ?? null;

        if (isset($values['pop_count']) && isset($values['pop_interval']) && isset($values['pop_burst'])) {
            $popConfig = new SiteCodeConfigPops(
                (int)$values['pop_count'],
                (int)$values['pop_interval'],
                (int)$values['pop_burst']
            );
        } else {
            $popConfig = null;
        }

        return new SiteCodeConfig($isProxy, $isFallback, $isBlock, $minCpm, $fallbackRate, $popConfig);
    }

    private function filterBoolean(array $values, string $key): bool
    {
        return filter_var($values[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
