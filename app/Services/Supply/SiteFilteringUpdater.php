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
use Adshares\Classify\Domain\Model\Classification;

class SiteFilteringUpdater
{
    public const INTERNAL_CLASSIFIER_NAMESPACE = 'classify';

    public const KEYWORD_CLASSIFIED = 'classified';

    public const KEYWORD_CLASSIFIED_VALUE = ['1'];

    private const RESERVED_NAMESPACE_TYPE = 'type';

    private const NAMESPACE_SEPARATOR = ':';

    public function addClassificationToFiltering(Site $site): void
    {
        $siteRequires = $site->site_requires ?: [];
        $siteExcludes = $site->site_excludes ?: [];

        unset($siteRequires[self::INTERNAL_CLASSIFIER_NAMESPACE]);
        unset($siteExcludes[self::INTERNAL_CLASSIFIER_NAMESPACE]);

        foreach ($this->extractClassifiers($siteExcludes, $siteRequires) as $classifier) {
            $siteRequires[$classifier . self::NAMESPACE_SEPARATOR . self::KEYWORD_CLASSIFIED] =
                self::KEYWORD_CLASSIFIED_VALUE;
        }

        $siteExcludes[self::INTERNAL_CLASSIFIER_NAMESPACE] = [
            $this->getClassificationForRejectedBanners($site->user_id)->keyword(),
        ];

        $baseRequires = json_decode(config('app.site_filtering_require') ?? '', true);
        if (is_array($baseRequires)) {
            $siteRequires = array_merge_recursive($siteRequires, $baseRequires);
        }

        $baseExcludes = json_decode(config('app.site_filtering_exclude') ?? '', true);
        if (is_array($baseExcludes)) {
            $siteExcludes = array_merge_recursive($siteExcludes, $baseExcludes);
        }

        $site->site_excludes = array_map([__CLASS__, 'normalize'], $siteExcludes);
        $site->site_requires = array_map([__CLASS__, 'normalize'], $siteRequires);
        $site->save();
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

    private function getClassificationForRejectedBanners(int $publisherId): Classification
    {
        return new Classification(self::INTERNAL_CLASSIFIER_NAMESPACE, $publisherId, false);
    }

    private function extractClassifiers(array $siteExcludes, array $siteRequires): array
    {
        $keys = array_merge(array_keys($siteExcludes), array_keys($siteRequires));
        $classifiers = [];
        foreach ($keys as $key) {
            if (self::RESERVED_NAMESPACE_TYPE === $key) {
                continue;
            }

            if (false !== ($index = strpos($key, self::NAMESPACE_SEPARATOR))) {
                $key = substr($key, 0, $index);
            }

            if (!in_array($key, $classifiers, true)) {
                $classifiers[] = $key;
            }
        }

        return $classifiers;
    }
}
