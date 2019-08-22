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

namespace Adshares\Common\Application\Model\Selector;

use function str_replace;

final class OptionValue
{
    /** @var string */
    private $label;

    /** @var string */
    private $value;

    /** @var string|null */
    private $description;

    public function __construct(string $label, string $value, ?string $description = null)
    {
        $this->label = $label;
        $this->value = $value;
        $this->description = $description;
    }

    public function toArray(): array
    {
        $array = [
            'label' => $this->label,
            'value' => str_replace('-', '_', $this->value),
        ];

        if ($this->description) {
            $array['description'] = $this->description;
        }

        return $array;
    }
}
