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

namespace Adshares\Common\Domain\ValueObject;

use Adshares\Common\Domain\ValueObject\Exception\InvalidVersionStringException;
use InvalidArgumentException;

final class SemVer
{
    /** @var int */
    private $major;
    /** @var int */
    private $minor;
    /** @var int */
    private $patch;

    private function __construct(int $major, int $minor, int $patch)
    {
        $this->major = $major;
        $this->minor = $minor;
        $this->patch = $patch;
    }

    public static function fromString(string $string): self
    {
        if (!preg_match(
            '#^'
            .'(v|release\-)?'
            .'(?P<core>(?:[0-9]|[1-9][0-9]+)(?:\.(?:[0-9]|[1-9][0-9]+)){2})'
            .'$#',
            $string,
            $parts
        )) {
            throw new InvalidArgumentException("'$string' is not valid.");
        }

        [$major, $minor, $patch] = explode('.', $parts['core']);

        return new self((int)$major, (int)$minor, (int)$patch);
    }

    public function __toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }

}
