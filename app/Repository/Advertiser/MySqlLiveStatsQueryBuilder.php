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

namespace Adshares\Adserver\Repository\Advertiser;

use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use Adshares\Advertiser\Repository\StatsRepository;
use DateTimeInterface;

use function in_array;
use function sprintf;

class MySqlLiveStatsQueryBuilder extends MySqlQueryBuilder
{
    private const ALLOWED_TYPES = [
        StatsRepository::TYPE_VIEW,
        StatsRepository::TYPE_VIEW_UNIQUE,
        StatsRepository::TYPE_VIEW_ALL,
        StatsRepository::TYPE_CLICK,
        StatsRepository::TYPE_CLICK_ALL,
        StatsRepository::TYPE_STATS,
    ];

    public function __construct(string $type)
    {
        $this->selectBaseColumns($type);
        $this->withoutRemovedCampaigns();

        parent::__construct($type);
    }

    protected function isTypeAllowed(string $type): bool
    {
        return in_array($type, self::ALLOWED_TYPES, true);
    }

    protected function getTableName(): string
    {
        return 'event_logs e';
    }

    private function selectBaseColumns(string $type): void
    {
        switch ($type) {
            case StatsRepository::TYPE_VIEW:
                $this->column(
                    "SUM(IF(e.event_type = 'view' AND (e.human_score >= 0.5 OR e.human_score IS NULL), 1, 0)) AS c"
                );
                break;
            case StatsRepository::TYPE_VIEW_ALL:
                $this->column("SUM(IF(e.event_type = 'view', 1, 0)) AS c");
                break;
            case StatsRepository::TYPE_CLICK:
                $this->column(
                    "SUM(IF(e.event_type = 'view' AND e.is_view_clicked = 1 "
                    . 'AND (e.human_score >= 0.5 OR e.human_score IS NULL), 1, 0)) AS c'
                );
                break;
            case StatsRepository::TYPE_CLICK_ALL:
                $this->column("SUM(IF(e.event_type = 'view' AND e.is_view_clicked = 1, 1, 0)) AS c");
                break;
            case StatsRepository::TYPE_VIEW_UNIQUE:
                $this->column(
                    "COUNT(DISTINCT (CASE WHEN e.event_type = 'view' "
                    . 'AND (e.human_score >= 0.5 OR e.human_score IS NULL) '
                    . 'THEN IFNULL(e.user_id, e.tracking_id) END)) AS c'
                );
                break;
            case StatsRepository::TYPE_STATS:
                $this->selectBaseStatsColumns();
                break;
        }
    }

    private function selectBaseStatsColumns(): void
    {
        $this->column(
            "SUM(IF(e.event_type = 'view' AND e.is_view_clicked = 1 "
            . 'AND (e.human_score >= 0.5 OR e.human_score IS NULL), 1, 0)) AS clicks'
        );
        $this->column(
            "SUM(IF(e.event_type = 'view' AND (e.human_score >= 0.5 OR e.human_score IS NULL), 1, 0)) AS views"
        );
        $this->column('0 AS cost');
    }

    private function withoutRemovedCampaigns(): void
    {
        $this->join('campaigns c', 'c.uuid = e.campaign_id');
        $this->where('c.deleted_at IS NULL');
    }

    public function setAdvertiserId(string $advertiserId): self
    {
        $this->where(sprintf('e.advertiser_id = 0x%s', $advertiserId));

        return $this;
    }

    public function appendAdvertiserIdGroupBy(): self
    {
        $this->join('users', 'users.uuid = e.advertiser_id');
        $this->column('users.id AS advertiser_id');
        $this->column('users.email AS advertiser_email');
        $this->groupBy('users.uuid');

        return $this;
    }

    public function setDateRange(DateTimeInterface $dateStart, DateTimeInterface $dateEnd): self
    {
        $this->where(
            sprintf(
                "e.created_at BETWEEN '%s' AND '%s'",
                $this->convertDateTimeToMySqlDate($dateStart),
                $this->convertDateTimeToMySqlDate($dateEnd)
            )
        );

        return $this;
    }

    public function appendResolution(string $resolution): self
    {
        switch ($resolution) {
            case StatsRepository::RESOLUTION_HOUR:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->column('DAY(e.created_at) AS d');
                $this->column('HOUR(e.created_at) AS h');
                $this->groupBy('y');
                $this->groupBy('m');
                $this->groupBy('d');
                $this->groupBy('h');
                break;
            case StatsRepository::RESOLUTION_DAY:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->column('DAY(e.created_at) AS d');
                $this->groupBy('y');
                $this->groupBy('m');
                $this->groupBy('d');
                break;
            case StatsRepository::RESOLUTION_WEEK:
                $this->column('YEARWEEK(e.created_at, 3) as yw');
                $this->groupBy('yw');
                break;
            case StatsRepository::RESOLUTION_MONTH:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->groupBy('y');
                $this->groupBy('m');
                break;
            case StatsRepository::RESOLUTION_QUARTER:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('QUARTER(e.created_at) as q');
                $this->groupBy('y');
                $this->groupBy('q');
                break;
            case StatsRepository::RESOLUTION_YEAR:
            default:
                $this->column('YEAR(e.created_at) AS y');
                $this->groupBy('y');
                break;
        }

        return $this;
    }

    public function appendCampaignIdWhereClause(string $campaignId): self
    {
        $this->where(sprintf('e.campaign_id = 0x%s', $campaignId));

        return $this;
    }

    public function appendBannerIdWhereClause(string $bannerId): self
    {
        $this->where(sprintf('e.banner_id = 0x%s', $bannerId));

        return $this;
    }

    public function appendBannerIdGroupBy(): self
    {
        $this->join('banners b', 'b.uuid = e.banner_id');
        $this->column('b.id AS banner_id');
        $this->column('b.name AS banner_name');
        $this->where('e.banner_id IS NOT NULL');
        $this->groupBy('b.id');

        return $this;
    }

    public function appendCampaignIdGroupBy(): self
    {
        $this->column('c.id AS campaign_id');
        $this->column('c.name AS campaign_name');
        $this->groupBy('c.id');
        $this->having('clicks>0');
        $this->having('views>0');
        $this->having('cost>0');

        return $this;
    }

    public function appendDomainGroupBy(): self
    {
        $this->column("IFNULL(e.domain, '') AS domain");
        $this->groupBy("IFNULL(e.domain, '')");

        return $this;
    }
}
