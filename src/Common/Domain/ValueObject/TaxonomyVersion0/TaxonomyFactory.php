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

namespace Adshares\Common\Domain\ValueObject\TaxonomyVersion0;

use Adshares\Common\Domain\ValueObject\SemVer;

final class TaxonomyFactory
{
    public static function fromJson(string $json): Taxonomy
    {
        return self::fromArray(json_decode($json, true));
    }

    public static function fromArray(array $taxonomy): Taxonomy
    {
        return new Taxonomy(
            Schema::fromString($taxonomy['$schema']),
            SemVer::fromString($taxonomy['version']),
            ...array_map(function (array $item) {
                return TaxonomyItemFactory::fromAdUser($item);
            }, $taxonomy['data'])
        );
    }

}
