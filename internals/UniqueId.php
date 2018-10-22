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

namespace Internals;

use Lib\Id;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UniqueId implements Id
{
    /** @var UuidInterface */
    private $id;

    public function __toString(): string
    {
        return $this->id->toString();
    }

    public static function fromString(string $id): self
    {
        $uuid = new self();
        $uuid->id = Uuid::fromString($id);

        return $uuid;
    }

    public static function random(): self
    {
        $uuid = new self();
        $uuid->id = Uuid::uuid4();

        return $uuid;
    }

    private function __construct() { }
}
