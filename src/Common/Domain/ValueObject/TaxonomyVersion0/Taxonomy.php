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

use Adshares\Common\Domain\Adapter\ArrayCollection;
use Adshares\Common\Domain\ValueObject\SemVer;
use Adshares\Common\Domain\ValueObject\TargetingOptions;
use function array_map;

final class Taxonomy extends ArrayCollection
{
    /** @var Schema */
    private $schema;
    /** @var SemVer */
    private $version;

    public function __construct(Schema $schema, SemVer $version, TaxonomyItem...$items)
    {
        $this->schema = $schema;
        $this->version = $version;

        parent::__construct($items);
    }

    public function toTargetingOptions(): TargetingOptions
    {
        $items = array_map(
            function (TaxonomyItem $item) {
                return $item->toTargetingOption();
            }, $this->toArray());

        return new TargetingOptions(...$items);
    }
}
