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
    public const VIEW_TYPE = 'VIEW';
    public const CLICK_TYPE = 'CLICK';
    public const CPC_TYPE = 'CPC';
    public const CPM_TYPE = 'CPM';
    public const SUM_TYPE = 'SUM';
    public const CTR_TYPE = 'CTR';

    public const HOUR_RESOLUTION = 'HOUR';
    public const DAY_RESOLUTION = 'DAY';
    public const WEEK_RESOLUTION = 'WEEK';
    public const MONTH_RESOLUTION = 'MONTH';
    public const QUARTER_RESOLUTION = 'QUARTER';
    public const YEAR_RESOLUTION = 'YEAR';

    private const ALLOWED_TYPES = [
        self::VIEW_TYPE,
        self::CLICK_TYPE,
        self::CPC_TYPE,
        self::CPM_TYPE,
        self::SUM_TYPE,
        self::CTR_TYPE,
    ];

    private const ALLOWED_RESOLUTION = [
        self::HOUR_RESOLUTION,
        self::DAY_RESOLUTION,
        self::WEEK_RESOLUTION,
        self::MONTH_RESOLUTION,
        self::QUARTER_RESOLUTION,
        self::YEAR_RESOLUTION,
    ];

    /** @var int */
    private $advertiserId;

    /** @var string  */
    private $type;

    /** @var string  */
    private $resolution;

    /** @var DateTime */
    private $dateStart;

    /** @var DateTime */
    private $dateEnd;

    /** @var int|null */
    private $campaignId;

    /** @var int|null */
    private $bannerId;

    public function __construct(
        int $advertiserId,
        string $type,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
    ) {
        if (in_array($type, self::ALLOWED_TYPES, true)) {
            throw new InvalidChartInputException(sprintf('Unsupported chart type `%s`.', $type));
        }

        if (in_array($resolution, self::ALLOWED_RESOLUTION, true)) {
            throw new InvalidChartInputException(sprintf('Unsupported chart resolution `%s`.', $resolution));
        }

        if ($dateEnd > $dateStart) {
            throw new InvalidChartInputException(sprintf(
                'Start date (%s) must be earlier than end date (%s).',
                $dateStart->format(DateTime::ATOM),
                $dateEnd->format(DateTime::ATOM)
            ));
        }

        $this->type = $type;
        $this->resolution = $resolution;
        $this->advertiserId = $advertiserId;
        $this->campaignId = $campaignId;
        $this->bannerId = $bannerId;
        $this->dateStart = $dateStart;
        $this->dateEnd = $dateEnd;
    }

    public function getAdvertiserId(): int
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

    public function getCampaignId(): ?int
    {
        return $this->campaignId;
    }

    public function getBannerId(): ?int
    {
        return $this->bannerId;
    }
}
