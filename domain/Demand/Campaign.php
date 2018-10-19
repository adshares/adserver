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

namespace AdServer\Demand;

use DateInterval;
use http\Url;

final class Campaign
{
    /** @var CampaignTargeting */
    private $targeting;
    /** @var string */
    private $name;
    /** @var Url */
    private $landingPage;
    /** @var DateInterval */
    private $duration;
    /** @var Advertiser */
    private $owner;

    public function __construct(
        Advertiser $owner,
        string $name,
        CampaignTargeting $targeting,
        Url $landingPage,
        DateInterval $duration
    ) {
        $this->owner = $owner;
        $this->name = $name;
        $this->targeting = $targeting;
        $this->landingPage = $landingPage;
        $this->duration = $duration;
    }
}
