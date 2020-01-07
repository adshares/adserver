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

namespace Adshares\Adserver\Repository\Advertiser;

use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use Adshares\Advertiser\Repository\StatsRepository;
use DateTime;
use function in_array;
use function sprintf;

class MySqlAggregatedStatsReportQueryBuilder extends MySqlQueryBuilder
{
    protected const TABLE_NAME = 'event_logs_hourly e';

    private const ALLOWED_TYPES = [
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
        return self::TABLE_NAME;
    }

    private function selectBaseColumns(string $type): void
    {
        if (StatsRepository::TYPE_STATS_REPORT === $type) {
                $this->selectBaseStatsReportColumns();
        }
    }

    private function selectBaseStatsReportColumns(): void
    {
        $this->column('SUM(e.clicks) AS clicks');
        $this->column('SUM(e.views) AS views');
        $this->column('SUM(e.cost) AS cost');
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
        $this->column('e.advertiser_id AS advertiser_id');
        $this->groupBy('e.advertiser_id');

        return $this;
    }

    public function setDateRange(DateTime $dateStart, DateTime $dateEnd): self
    {
        $this->where(
            sprintf(
                'e.hour_timestamp BETWEEN \'%s\' AND \'%s\'',
                $this->convertDateTimeToMySqlDate($dateStart),
                $this->convertDateTimeToMySqlDate($dateEnd)
            )
        );

        return $this;
    }

    public function appendCampaignIdWhereClause(string $campaignId): self
    {
        $this->where(sprintf('e.campaign_id = 0x%s', $campaignId));

        return $this;
    }

    public function appendBannerIdGroupBy(): self
    {
        $this->column('e.banner_id AS banner_id');
        $this->where('e.banner_id IS NOT NULL');
        $this->groupBy('e.banner_id');

        return $this;
    }

    public function appendCampaignIdGroupBy(): self
    {
        $this->column('e.campaign_id AS campaign_id');
        $this->groupBy('e.campaign_id');
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
