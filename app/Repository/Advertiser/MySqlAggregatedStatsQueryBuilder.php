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

class MySqlAggregatedStatsQueryBuilder extends MySqlQueryBuilder
{
    private const ALLOWED_TYPES = [
        StatsRepository::TYPE_VIEW,
        StatsRepository::TYPE_VIEW_UNIQUE,
        StatsRepository::TYPE_VIEW_ALL,
        StatsRepository::TYPE_CLICK,
        StatsRepository::TYPE_CLICK_ALL,
        StatsRepository::TYPE_SUM,
        StatsRepository::TYPE_SUM_BY_PAYMENT,
        StatsRepository::TYPE_STATS,
        StatsRepository::TYPE_STATS_REPORT,
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
        return (StatsRepository::TYPE_STATS_REPORT === $this->getType())
            ? 'event_logs_hourly e' : 'event_logs_hourly_stats e';
    }

    private function selectBaseColumns(string $type): void
    {
        switch ($type) {
            case StatsRepository::TYPE_VIEW:
                $this->column('SUM(e.views) AS c');
                break;
            case StatsRepository::TYPE_VIEW_ALL:
                $this->column('SUM(e.views_all) AS c');
                break;
            case StatsRepository::TYPE_CLICK:
                $this->column('SUM(e.clicks) AS c');
                break;
            case StatsRepository::TYPE_CLICK_ALL:
                $this->column('SUM(e.clicks_all) AS c');
                break;
            case StatsRepository::TYPE_VIEW_UNIQUE:
                $this->column('SUM(e.views_unique) AS c');
                break;
            case StatsRepository::TYPE_SUM:
                $this->column('SUM(e.cost) AS c');
                break;
            case StatsRepository::TYPE_SUM_BY_PAYMENT:
                $this->column('SUM(e.cost_payment) AS c');
                break;
            case StatsRepository::TYPE_STATS:
                $this->selectBaseStatsColumns();
                break;
            case StatsRepository::TYPE_STATS_REPORT:
                $this->selectBaseStatsReportColumns();
                break;
        }
    }

    private function selectBaseStatsColumns(): void
    {
        $this->column('SUM(e.clicks) AS clicks');
        $this->column('SUM(e.views) AS views');
        $this->column('SUM(e.cost) AS cost');
    }

    private function selectBaseStatsReportColumns(): void
    {
        $this->selectBaseStatsColumns();

        $this->column('SUM(e.clicks_all) AS clicksAll');
        $this->column('SUM(e.views_all) AS viewsAll');
        $this->column('SUM(e.views_unique) AS viewsUnique');
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
                "e.hour_timestamp BETWEEN '%s' AND '%s'",
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
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->column('MONTH(e.hour_timestamp) as m');
                $this->column('DAY(e.hour_timestamp) AS d');
                $this->column('HOUR(e.hour_timestamp) AS h');
                $this->groupBy('YEAR(e.hour_timestamp)');
                $this->groupBy('MONTH(e.hour_timestamp)');
                $this->groupBy('DAY(e.hour_timestamp)');
                $this->groupBy('HOUR(e.hour_timestamp)');
                break;
            case StatsRepository::RESOLUTION_DAY:
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->column('MONTH(e.hour_timestamp) as m');
                $this->column('DAY(e.hour_timestamp) AS d');

                $this->groupBy('YEAR(e.hour_timestamp)');
                $this->groupBy('MONTH(e.hour_timestamp)');
                $this->groupBy('DAY(e.hour_timestamp)');
                break;
            case StatsRepository::RESOLUTION_WEEK:
                $this->column('YEARWEEK(e.hour_timestamp, 3) as yw');
                $this->groupBy('YEARWEEK(e.hour_timestamp, 3)');
                break;
            case StatsRepository::RESOLUTION_MONTH:
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->column('MONTH(e.hour_timestamp) as m');
                $this->groupBy('YEAR(e.hour_timestamp)');
                $this->groupBy('MONTH(e.hour_timestamp)');
                break;
            case StatsRepository::RESOLUTION_QUARTER:
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->column('QUARTER(e.hour_timestamp) as q');
                $this->groupBy('YEAR(e.hour_timestamp)');
                $this->groupBy('QUARTER(e.hour_timestamp)');
                break;
            case StatsRepository::RESOLUTION_YEAR:
            default:
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->groupBy('YEAR(e.hour_timestamp)');
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

    public function appendAnyBannerId(): self
    {
        $this->where('e.banner_id IS NULL');

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

        if (StatsRepository::TYPE_STATS_REPORT === $this->getType()) {
            $this->having('clicksAll>0');
            $this->having('viewsAll>0');
            $this->having('viewsUnique>0');
        }

        return $this;
    }

    public function appendDomainGroupBy(): self
    {
        $this->column("IFNULL(e.domain, '') AS domain");
        $this->groupBy("IFNULL(e.domain, '')");

        return $this;
    }
}
