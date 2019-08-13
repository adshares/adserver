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

namespace Adshares\Common\Application\Dto\Taxonomy\Item;

use Adshares\Common\Application\Model\Selector\OptionValue;

final class Value
{
    /** @var string */
    private $value;
    /** @var string */
    private $label;
    /** @var string */
    private $description;

    public function __construct(string $value, string $label, ?string $description = null)
    {
        $this->value = $value;
        $this->label = $label;
        $this->description = $description;
    }

    public function toOptionValue(): OptionValue
    {
        return new OptionValue($this->label, $this->value, $this->description);
    }
}
