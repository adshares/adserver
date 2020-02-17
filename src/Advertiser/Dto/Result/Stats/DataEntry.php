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

namespace Adshares\Advertiser\Dto\Result\Stats;

class DataEntry
{
    /** @var Calculation */
    private $calculation;

    /** @var int */
    private $campaignId;

    /** @var string */
    private $campaignName;

    /** @var int|null */
    private $bannerId;

    /** @var string|null */
    private $bannerName;

    /** @var string|null */
    private $advertiserId;

    public function __construct(
        Calculation $calculation,
        int $campaignId,
        string $campaignName,
        ?int $bannerId = null,
        ?string $bannerName = null,
        ?string $advertiserId = null
    ) {
        $this->calculation = $calculation;
        $this->campaignId = $campaignId;
        $this->campaignName = $campaignName;
        $this->bannerId = $bannerId;
        $this->bannerName = $bannerName;
        $this->advertiserId = $advertiserId;
    }

    public function toArray(): array
    {
        $data = $this->calculation->toArray();
        $data['campaignId'] = $this->campaignId;
        $data['campaignName'] = $this->campaignName;

        if ($this->bannerId && $this->bannerName) {
            $data['bannerId'] = $this->bannerId;
            $data['bannerName'] = $this->bannerName;
        }

        if ($this->advertiserId) {
            $data['advertiserId'] = $this->advertiserId;
        }

        return $data;
    }
}
