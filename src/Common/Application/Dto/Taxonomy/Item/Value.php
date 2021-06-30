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

namespace Adshares\Common\Application\Dto\Taxonomy\Item;

use Adshares\Common\Application\Model\Selector\OptionValue;

final class Value
{
    /** @var string */
    private $value;
    /** @var string */
    private $label;
    /** @var string|null */
    private $description;
    /** @var array */
    private $values;

    public function __construct(string $value, string $label, array $values, ?string $description = null)
    {
        $this->value = $value;
        $this->label = $label;
        $this->description = $description;
        $this->values = $values;
    }

    public function toOptionValue(): OptionValue
    {
        if ($this->values) {
            $options = array_map(
                function ($value) {
                    /** @var $value Value */
                    return $value->toOptionValue();
                },
                $this->values
            );
        } else {
            $options = [];
        }

        return new OptionValue($this->label, $this->value, $options, $this->description);
    }
}
