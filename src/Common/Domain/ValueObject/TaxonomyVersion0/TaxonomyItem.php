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

use Adshares\Common\Domain\ValueObject\TargetingOption;
use Adshares\Common\Domain\ValueObject\TargetingOptions;
use Adshares\Common\Domain\ValueObject\TargetingOptionValue;
use InvalidArgumentException;
use function array_map;

final class TaxonomyItem
{
    /** @var Type */
    private $type;
    /** @var string */
    private $key;
    /** @var string */
    private $label;
    /** @var Value[] */
    private $values;

    public function __construct(Type $type, string $key, string $label, Value ...$values)
    {
        $this->type = $type;
        $this->key = $key;
        $this->label = $label;

        $this->validateValues(...$values);

        $this->values = $values;
    }

    private function validateValues(Value ...$values): void
    {
        if ($this->type->is(Type::TYPE_DICTIONARY) && empty($values)) {
            throw new InvalidArgumentException('Dictionary type needs predefined values. None given.');
        }
    }

    public function toTargetingOption(): TargetingOption
    {
        if ($this->type->is(Type::TYPE_DICTIONARY)) {
            $values = array_map(function (Value $value) {
                return $value->toTargetingOptionValue();
            }, $this->values);

            return new TargetingOption(
                null,
                $this->key,
                $this->label,
                null,
                new TargetingOptions(),
                ...$values
            );
        }

        if ($this->type->is(Type::TYPE_LIST)) {
            return new TargetingOption(
                null,
                $this->key,
                $this->label,
                true,
                new TargetingOptions()
            );
        }

        if ($this->type->is(Type::TYPE_BOOLEAN)) {
            return new TargetingOption(
                null,
                $this->key,
                $this->label,
                true,
                new TargetingOptions(),
                ...[
                    new TargetingOptionValue('Yes', 'true'),
                    new TargetingOptionValue('No', 'false'),
                ]
            );
        }

        return new TargetingOption((string)$this->type, $this->key, $this->label, false, new TargetingOptions());
    }
}
