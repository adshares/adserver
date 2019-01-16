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

namespace Adshares\Advertiser\Dto;

use DateTime;
use function in_array;

final class ChartInput
{
    public const VIEW_TYPE = 'view';
    public const CLICK_TYPE = 'click';
    public const CPC_TYPE = 'cpc';
    public const CPM_TYPE = 'cpm';
    public const SUM_TYPE = 'sum';
    public const CTR_TYPE = 'ctr';

    public const HOUR_RESOLUTION = 'hour';
    public const DAY_RESOLUTION = 'day';
    public const WEEK_RESOLUTION = 'week';
    public const MONTH_RESOLUTION = 'month';
    public const QUARTER_RESOLUTION = 'quarter';
    public const YEAR_RESOLUTION = 'year';

    private const ALLOWED_TYPES = [
        self::VIEW_TYPE,
        self::CLICK_TYPE,
        self::CPC_TYPE,
        self::CPM_TYPE,
        self::SUM_TYPE,
        self::CTR_TYPE,
    ];

    private const ALLOWED_RESOLUTIONS = [
        self::HOUR_RESOLUTION,
        self::DAY_RESOLUTION,
        self::WEEK_RESOLUTION,
        self::MONTH_RESOLUTION,
        self::QUARTER_RESOLUTION,
        self::YEAR_RESOLUTION,
    ];

    /** @var string */
    private $advertiserId;

    /** @var string  */
    private $type;

    /** @var string  */
    private $resolution;

    /** @var DateTime */
    private $dateStart;

    /** @var DateTime */
    private $dateEnd;

    /** @var string|null */
    private $campaignId;

    public function __construct(
        string $advertiserId,
        string $type,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ) {
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidInputException(sprintf('Unsupported chart type `%s`.', $type));
        }

        if (!in_array($resolution, self::ALLOWED_RESOLUTIONS, true)) {
            throw new InvalidInputException(sprintf('Unsupported chart resolution `%s`.', $resolution));
        }

        if ($dateEnd < $dateStart) {
            throw new InvalidInputException(sprintf(
                'Start date (%s) must be earlier than end date (%s).',
                $dateStart->format(DateTime::ATOM),
                $dateEnd->format(DateTime::ATOM)
            ));
        }

        $this->type = $type;
        $this->resolution = $resolution;
        $this->advertiserId = $advertiserId;
        $this->campaignId = $campaignId;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
    }

    public function getAdvertiserId(): string
    {
        return $this->advertiserId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getResolution(): string
    {
        return $this->resolution;
    }

    public function getDateStart(): DateTime
    {
        return $this->dateStart;
    }

    public function getDateEnd(): DateTime
    {
        return $this->dateEnd;
    }

    public function getCampaignId(): ?string
    {
        return $this->campaignId;
    }
}
