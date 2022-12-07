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

use Adshares\Common\Application\Dto\TaxonomyV1;
use Adshares\Common\Domain\ValueObject\SemVer;
use Adshares\Common\Domain\ValueObject\Taxonomy\Schema;
use ErrorException;
use GuzzleHttp\Utils;
use Illuminate\Support\Facades\Log;

final class TaxonomyV1Factory
{
    public static function fromJson(string $json): TaxonomyV1
    {
        return self::fromArray(Utils::jsonDecode($json, true));
    }

    public static function fromArray(array $taxonomy): TaxonomyV1
    {
        $schema = Schema::fromString($taxonomy['$schema'] ?? 'urn:x-adshares:taxonomy');

        $fallbackVersion = ($taxonomy['meta'] ?? false) ? $taxonomy['meta']['version'] : '0.0.0';
        $version = SemVer::fromString($taxonomy['$version'] ?? $fallbackVersion);

        if (isset($taxonomy['data'])) {
            try {
                $items = array_map(
                    function (array $item) {
                        return TaxonomyV1ItemFactory::fromArray($item);
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
                return TaxonomyV1ItemFactory::fromArray($item);
            },
            $taxonomy['data']['user'] ?? []
        );

        $itemsSite = array_map(
            function (array $item) {
                return TaxonomyV1ItemFactory::fromArray($item);
            },
            $taxonomy['data']['site'] ?? []
        );

        $itemsDevice = array_map(
            function (array $item) {
                return TaxonomyV1ItemFactory::fromArray($item);
            },
            $taxonomy['data']['device'] ?? []
        );

        return new TaxonomyV1(
            $taxonomy,
            $schema,
            $version,
            TaxonomyV1ItemFactory::groupingItem('user', 'User', ...$itemsUser),
            TaxonomyV1ItemFactory::groupingItem('site', 'Site', ...$itemsSite),
            TaxonomyV1ItemFactory::groupingItem('device', 'Device', ...$itemsDevice),
            ...$items
        );
    }
}
