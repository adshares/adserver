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

namespace Adshares\Adserver\Services\Supply;

use Adshares\Adserver\Models\Site;

class SiteFilteringMatcher
{
    public static function checkClassification(Site $site, array $classification): bool
    {
        foreach ($site->site_requires ?? [] as $key => $values) {
            if (SiteFilteringUpdater::INTERNAL_CLASSIFIER_NAMESPACE === $key) {
                continue;
            }
            [$classifier, $name] = self::parseKey($key);

            if (!array_key_exists($classifier, $classification)) {
                return false;
            }
            if (!array_key_exists($name, $classification[$classifier])) {
                return false;
            }

            $intersect = array_intersect($values, $classification[$classifier][$name]);
            if (empty($intersect)) {
                return false;
            }
        }

        foreach ($site->site_excludes ?? [] as $key => $values) {
            if (SiteFilteringUpdater::INTERNAL_CLASSIFIER_NAMESPACE === $key) {
                continue;
            }
            [$classifier, $name] = self::parseKey($key);

            if (!array_key_exists($classifier, $classification)) {
                continue;
            }
            if (!array_key_exists($name, $classification[$classifier])) {
                continue;
            }

            $intersect = array_intersect($values, $classification[$classifier][$name]);
            if (!empty($intersect)) {
                return false;
            }
        }

        return true;
    }

    private static function parseKey(string $key): array
    {
        return strpos($key, ':') !== false ? explode(':', $key) : [$key, $key];
    }
}
