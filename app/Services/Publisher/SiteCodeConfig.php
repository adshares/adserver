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

namespace Adshares\Adserver\Services\Publisher;

use Adshares\Common\Exception\InvalidArgumentException;

class SiteCodeConfig
{
    /** @var bool */
    private $isUserResponsibleForMainJsProxy;

    /** @var bool */
    private $isCustomFallback;

    /** @var bool */
    private $isAdBlockOnly;

    /** @var string|null */
    private $minCpm;

    /** @var string|null */
    private $fallbackRate;

    /** @var SiteCodeConfigPops|null */
    private $configPops;

    public function __construct(
        bool $isUserResponsibleForMainJsProxy,
        bool $isCustomFallback,
        bool $isAdBlockOnly,
        ?string $minCpm = null,
        ?string $fallbackRate = null,
        ?SiteCodeConfigPops $configPops = null
    ) {
        if (null !== $minCpm && !is_numeric($minCpm)) {
            throw new InvalidArgumentException('min cpm must be numeric');
        }

        $this->isUserResponsibleForMainJsProxy = $isUserResponsibleForMainJsProxy;
        $this->isCustomFallback = $isCustomFallback;
        $this->isAdBlockOnly = $isAdBlockOnly;
        $this->minCpm = $minCpm;
        $this->fallbackRate = $fallbackRate;
        $this->configPops = $configPops;
    }

    public static function default(): self
    {
        return new self(false, false, false);
    }

    public function isUserResponsibleForMainJsProxy(): bool
    {
        return $this->isUserResponsibleForMainJsProxy;
    }

    public function isCustomFallback(): bool
    {
        return $this->isCustomFallback;
    }

    public function isAdBlockOnly(): bool
    {
        return $this->isAdBlockOnly;
    }

    public function getMinCpm(): ?string
    {
        return $this->minCpm;
    }

    public function getFallbackRate(): ?string
    {
        return $this->fallbackRate;
    }

    public function getConfigPops(): SiteCodeConfigPops
    {
        if (null === $this->configPops) {
            $this->configPops = new SiteCodeConfigPops();
        }

        return $this->configPops;
    }
}
