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

namespace Adshares\Adserver\Dto\Response\Classifier;

use Illuminate\Contracts\Support\Arrayable;

class Item implements Arrayable
{
    /** @var int */
    private $bannerId;
    /** @var string */
    private $type;
    /** @var int */
    private $width;
    /** @var int */
    private $height;
    /** @var string */
    private $sourceHost;
    /** @var int */
    private $budget;
    /** @var int */
    private $cpm;
    /** @var int */
    private $cpc;
    /** @var bool|null */
    private $classifiedGlobal;
    /** @var bool|null */
    private $classifiedSite;

    public function __construct(
        int $bannerId,
        string $type,
        int $width,
        int $height,
        string $sourceHost,
        int $budget,
        int $cpm,
        int $cpc,
        ?bool $classifiedGlobal = null,
        ?bool $classifiedSite = null
    ) {
        $this->bannerId = $bannerId;
        $this->type = $type;
        $this->width = $width;
        $this->height = $height;
        $this->sourceHost = $sourceHost;
        $this->budget = $budget;
        $this->cpm = $cpm;
        $this->cpc = $cpc;
        $this->classifiedGlobal = $classifiedGlobal;
        $this->classifiedSite = $classifiedSite;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'banner_id' => $this->bannerId,
            'type' => $this->type,
            'width' => $this->width,
            'height' => $this->height,
            'source_host' => $this->sourceHost,
            'budget' => $this->budget,
            'cpm' => $this->cpm,
            'cpc' => $this->cpc,
            'classified_global' => $this->classifiedGlobal,
            'classified_site' => $this->classifiedSite,
        ];
    }
}
