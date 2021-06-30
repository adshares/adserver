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

namespace Adshares\Publisher\Dto\Result\Stats;

use RuntimeException;

class DataCollection
{
    private $data = [];

    public function __construct(array $data)
    {
        $this->validate($data);

        $this->data = $data;
    }

    private function validate(array $data): void
    {
        foreach ($data as $entry) {
            if (!$entry instanceof DataEntry) {
                throw new RuntimeException('Invalid object in the collection.');
            }
        }
    }

    public function toArray(): array
    {
        $data = [];

        /** @var DataEntry $entry */
        foreach ($this->data as $entry) {
            $data[] = $entry->toArray();
        }

        return $data;
    }
}
