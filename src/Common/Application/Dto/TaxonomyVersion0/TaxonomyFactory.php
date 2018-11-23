<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Common\Application\Dto\TaxonomyVersion0;

use Adshares\Common\Domain\ValueObject\SemVer;
use Adshares\Common\Domain\ValueObject\Taxonomy\Schema;

final class TaxonomyFactory
{
    public static function fromJson(string $json): Taxonomy
    {
        return self::fromArray(json_decode($json, true));
    }

    public static function fromArray(array $taxonomy): Taxonomy
    {
        $schema = Schema::fromString($taxonomy['$schema'] ?? 'urn:x-adshares:taxonomy');
        $version = SemVer::fromString($taxonomy['version'] ?? $taxonomy['meta']['version']);
        $items = array_map(function (array $item) {
            return ItemFactory::fromArray($item);
        }, $taxonomy['data']);

        return new Taxonomy($schema, $version, ...$items);
    }
}
