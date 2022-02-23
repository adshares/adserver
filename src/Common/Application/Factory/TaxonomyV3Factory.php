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

namespace Adshares\Common\Application\Factory;

use Adshares\Common\Application\Dto\TaxonomyV3;
use Adshares\Common\Domain\ValueObject\SemVer;
use Adshares\Common\Domain\ValueObject\Taxonomy\Schema;
use ErrorException;
use Illuminate\Support\Facades\Log;

use function GuzzleHttp\json_decode;

final class TaxonomyV3Factory
{
    public static function fromJson(string $json): TaxonomyV3
    {
        return self::fromArray(json_decode($json, true));
    }

    public static function fromArray(array $taxonomy): TaxonomyV3
    {
        $schema = Schema::fromString($taxonomy['$schema'] ?? 'urn:x-adshares:taxonomy');

        $fallbackVersion = ($taxonomy['meta'] ?? false) ? $taxonomy['meta']['version'] : '0.0.0';
        $version = SemVer::fromString($taxonomy['$version'] ?? $fallbackVersion);

        if (isset($taxonomy['data'])) {
            try {
                $items = array_map(
                    function (array $item) {
                        return TaxonomyV3ItemFactory::fromArray($item);
                    },
                    $taxonomy['data']
                );
            } catch (ErrorException $e) {
                Log::info('This seems to be a newer version of Taxonomy.');
                $items = [];
            }
        } else {
            $items = [];
        }

        $itemsUser = array_map(
            function (array $item) {
                return TaxonomyV3ItemFactory::fromArray($item);
            },
            $taxonomy['data']['user'] ?? []
        );

        $itemsSite = array_map(
            function (array $item) {
                return TaxonomyV3ItemFactory::fromArray($item);
            },
            $taxonomy['data']['site'] ?? []
        );

        $itemsDevice = array_map(
            function (array $item) {
                return TaxonomyV3ItemFactory::fromArray($item);
            },
            $taxonomy['data']['device'] ?? []
        );

        return new TaxonomyV3(
            $taxonomy,
            $schema,
            $version,
            TaxonomyV3ItemFactory::groupingItem('user', 'User', ...$itemsUser),
            TaxonomyV3ItemFactory::groupingItem('site', 'Site', ...$itemsSite),
            TaxonomyV3ItemFactory::groupingItem('device', 'Device', ...$itemsDevice),
            ...$items
        );
    }
}
