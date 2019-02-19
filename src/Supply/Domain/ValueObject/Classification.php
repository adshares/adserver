<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Supply\Domain\ValueObject;

use function hex2bin;

class Classification
{
    /** @var string */
    private $signature;
    /** @var string */
    private $keyword;

    public function __construct(string $keyword, string $signature)
    {
        $this->signature = $signature;
        $this->keyword = $keyword;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function getSignature(): string
    {
        return hex2bin($this->signature);
    }

    public function equals(self $classification): bool
    {
        return $this->keyword === $classification->getKeyword();
    }

    public function toArray(): array
    {
        return [
            'signature' => $this->signature,
            'keyword' => $this->keyword,
        ];
    }
}
