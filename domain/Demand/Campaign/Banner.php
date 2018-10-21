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
declare(strict_types=1);

namespace AdServer\Demand\Campaign;

use Lib\Entity;

final class Banner implements Entity
{
    use Entity\Entity;
    /** @var Banner\Type */
    private $type;
    /** @var Banner\Dimensions */
    private $dimensions;
    /** @var Banner\Classification */
    private $classification;

    public function __construct(Banner\Type $type, Banner\Dimensions $dimensions)
    {
        $this->type = $type;
        $this->dimensions = $dimensions;
    }

    public function classify(Banner\Classification $classification): void
    {
        $this->classification = $classification;
    }
}
