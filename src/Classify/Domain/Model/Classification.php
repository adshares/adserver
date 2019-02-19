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

namespace Adshares\Classify\Domain\Model;

class Classification
{
    /** @var string */
    private $publisherId;
    /** @var string */
    private $bannerId;
    /** @var string */
    private $status;
    /** @var string */
    private $signature;
    /** @var string|null */
    private $siteId;
    /** @var string */
    private $namespace;

    public function __construct(
        string $namespace,
        string $publisherId,
        string $bannerId,
        ?string $signature = null,
        ?string $siteId = null,
        ?int $status = null
    )
    {
        $this->publisherId = $publisherId;
        $this->bannerId = $bannerId;
        $this->status = $status;
        $this->signature = $signature;
        $this->siteId = $siteId;
        $this->namespace = $namespace;
    }

    public static function createUnsigned(
        string $namespace,
        string $publisherId,
        string $bannerId,
        ?string $siteId = null,
        ?int $status = null
    ): self {
        return new self($namespace, $publisherId, $bannerId, null, $siteId, $status);
    }

    public function export(): array
    {
        return [
            'keyword' => $this->keyword(),
            'signature' => $this->signature,
        ];
    }

    public function keyword(): string
    {
        if ($this->siteId) {
            return sprintf('%s:%s:%s:%s', $this->namespace, $this->publisherId, $this->siteId, $this->status);
        }

        return sprintf('%s:%s:%s', $this->namespace, $this->publisherId, $this->status);
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function sign(string $signature): void
    {
        $this->signature = $signature;
    }
}
