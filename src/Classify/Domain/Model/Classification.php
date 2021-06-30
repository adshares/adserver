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

namespace Adshares\Classify\Domain\Model;

class Classification
{
    /** @var int */
    private $publisherId;
    /** @var bool */
    private $status;
    /** @var int|null */
    private $siteId;
    /** @var string */
    private $namespace;

    public function __construct(
        string $namespace,
        int $publisherId,
        bool $status,
        ?int $siteId = null
    ) {
        $this->namespace = $namespace;
        $this->publisherId = $publisherId;
        $this->status = $status;
        $this->siteId = $siteId;
    }

    public function getSiteId(): ?int
    {
        return $this->siteId;
    }

    public function export(): array
    {
        return [
            'keyword' => $this->keyword(),
        ];
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function keyword(): string
    {
        if ($this->siteId) {
            return sprintf(
                '%s:%s:%s',
                $this->publisherId,
                $this->siteId,
                (int)$this->status
            );
        }

        return sprintf(
            '%s:%s',
            $this->publisherId,
            (int)$this->status
        );
    }
}
