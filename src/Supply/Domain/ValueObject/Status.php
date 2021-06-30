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

namespace Adshares\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\Model\Exception\UnsupportedStatusTypeException;

class Status
{
    public const STATUS_PROCESSING = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_TO_DELETE = 2;
    public const STATUS_DELETED = 3;

    private const STATUS_ALLOWED = [
        self::STATUS_PROCESSING,
        self::STATUS_ACTIVE,
        self::STATUS_TO_DELETE,
        self::STATUS_DELETED,
    ];

    private $type;

    private function __construct(int $type)
    {
        $this->type = $type;
    }

    public static function active(): self
    {
        return new self(self::STATUS_ACTIVE);
    }

    public static function toDelete(): self
    {
        return new self(self::STATUS_TO_DELETE);
    }

    public static function deleted(): self
    {
        return new self(self::STATUS_DELETED);
    }

    public static function processing(): self
    {
        return new self(self::STATUS_PROCESSING);
    }

    public static function fromStatus(int $status): self
    {
        if (!in_array($status, self::STATUS_ALLOWED, true)) {
            throw new UnsupportedStatusTypeException(sprintf('Unsupported status %s.', $status));
        }

        return new self($status);
    }

    public function getStatus(): int
    {
        return $this->type;
    }
}
