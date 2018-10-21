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

namespace AdServer\Demand;

use DateInterval;
use Lib\Duration;
use Lib\Entity;
use Lib\Url;

final class Campaign implements Entity
{
    use Entity\EntityTrait;
    /** @var Advertiser */
    private $owner;
    /** @var string */
    private $name;
    /** @var Url */
    private $landingPage;
    /** @var DateInterval */
    private $duration;
    /** @var CampaignTargeting */
    private $targeting;

    public function __construct(
        Advertiser $owner,
        string $name,
        CampaignTargeting $targeting,
        Url $landingPage,
        Duration $duration
    ) {
        $this->owner = $owner;
        $this->name = $name;
        $this->targeting = $targeting;
        $this->landingPage = $landingPage;
        $this->duration = $duration;
    }
}
