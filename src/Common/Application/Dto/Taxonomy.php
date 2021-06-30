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

namespace Adshares\Common\Application\Dto;

use Adshares\Common\Application\Dto\Taxonomy\Item;
use Adshares\Common\Domain\Adapter\ArrayCollection;
use Adshares\Common\Domain\ValueObject\SemVer;
use Adshares\Common\Domain\ValueObject\Taxonomy\Schema;

final class Taxonomy extends ArrayCollection
{
    /** @var array */
    private $rawData;
    /** @var Schema */
    private $schema;
    /** @var SemVer */
    private $version;

    public function __construct(array $rawData, Schema $schema, SemVer $version, Item ...$items)
    {
        $this->rawData = $rawData;
        $this->schema = $schema;
        $this->version = $version;

        parent::__construct($items);
    }

    public function getRawData(): array
    {
        return $this->rawData;
    }
}
