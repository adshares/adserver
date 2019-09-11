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

namespace Adshares\Adserver\Services\Supply;

use Adshares\Adserver\Models\Site;
use Adshares\Classify\Domain\Model\Classification;

class SiteClassificationUpdater
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
            $siteRequires[$classifier.self::NAMESPACE_SEPARATOR.self::KEYWORD_CLASSIFIED] =
                self::KEYWORD_CLASSIFIED_VALUE;
        }

        $excludeKeywords = $this->getClassificationForRejectedBanners($site->user_id, $site->id);

        /** @var Classification $excludeKeyword */
        foreach ($excludeKeywords as $excludeKeyword) {
            $siteExcludes[self::INTERNAL_CLASSIFIER_NAMESPACE][] = $excludeKeyword->keyword();
        }

        $site->site_excludes = $siteExcludes;
        $site->site_requires = $siteRequires;
        $site->save();
    }

    private function getClassificationForRejectedBanners(int $publisherId, int $siteId): array
    {
        return [
            new Classification(self::INTERNAL_CLASSIFIER_NAMESPACE, $publisherId, false),
            new Classification(self::INTERNAL_CLASSIFIER_NAMESPACE, $publisherId, false, $siteId),
        ];
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
