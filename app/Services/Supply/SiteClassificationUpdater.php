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
    public function addInternalClassificationToFiltering(Site $site): void
    {
        $namespace = (string)config('app.classify_namespace');

        $siteRequires = $site->site_requires;
        $siteExcludes = $site->site_excludes;

        unset($siteRequires[$namespace]);
        unset($siteExcludes[$namespace]);

        $publisherId = $site->user_id;
        $siteId = $site->id;

        if ($site->require_classified) {
            list($requireKeywords, $excludeKeywords) =
                $this->getClassificationForPositiveCase($namespace, $publisherId, $siteId);

            /** @var Classification $requireKeyword */
            foreach ($requireKeywords as $requireKeyword) {
                $siteRequires[$requireKeyword->getNamespace()][] = $requireKeyword->keyword();
            }
            /** @var Classification $excludeKeyword */
            foreach ($excludeKeywords as $excludeKeyword) {
                $siteExcludes[$excludeKeyword->getNamespace()][] = $excludeKeyword->keyword();
            }
        }

        if ($site->exclude_unclassified) {
            $excludeKeywords = $this->getClassificationNotNegativeCase($namespace, $publisherId, $siteId);

            /** @var Classification $excludeKeyword */
            foreach ($excludeKeywords as $excludeKeyword) {
                $namespace = $excludeKeyword->getNamespace();
                $keyword = $excludeKeyword->keyword();

                if (!in_array($keyword, $siteExcludes[$namespace], true)) {
                    $siteExcludes[$namespace][] = $keyword;
                }
            }
        }

        $site->site_excludes = $siteExcludes;
        $site->site_requires = $siteRequires;
        $site->save();
    }

    private function getClassificationForPositiveCase(string $namespace, int $publisherId, int $siteId): array
    {
        $requireKeywords = [
            new Classification($namespace, $publisherId, true),
            new Classification($namespace, $publisherId, true, $siteId),
        ];
        $excludeKeywords = [
            new Classification($namespace, $publisherId, false, $siteId),
        ];

        return [$requireKeywords, $excludeKeywords];
    }

    private function getClassificationNotNegativeCase(string $namespace, int $publisherId, int $siteId): array
    {
        return [
            new Classification($namespace, $publisherId, false),
            new Classification($namespace, $publisherId, false, $siteId),
        ];
    }
}
