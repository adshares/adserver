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

namespace Adshares\Adserver\Repository\Publisher;

use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;
use function in_array;
use function sprintf;

class MySqlAggregatedStatsReportQueryBuilder extends MySqlQueryBuilder
{
    protected const TABLE_NAME = 'network_case_logs_hourly e';

    private const ALLOWED_TYPES = [
        StatsRepository::TYPE_STATS_REPORT,
    ];

    public function __construct(string $type)
    {
        $this->selectBaseColumns($type);
        $this->withoutRemovedSites();

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
        $this->column('SUM(e.revenue_case) AS revenue');

        $this->column('SUM(e.clicks_all) AS clicksAll');
        $this->column('SUM(e.views_all) AS viewsAll');
        $this->column('SUM(e.views_unique) AS viewsUnique');
    }

    private function withoutRemovedSites(): void
    {
        $this->join('sites s', 's.uuid = e.site_id');
        $this->where('s.deleted_at IS NULL');
    }

    public function setPublisherId(string $publisherId): self
    {
        $this->where(sprintf('e.publisher_id = 0x%s', $publisherId));

        return $this;
    }

    public function appendPublisherIdGroupBy(): self
    {
        $this->column('e.publisher_id AS publisher_id');
        $this->groupBy('e.publisher_id');

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

    public function appendSiteIdWhereClause(string $siteId): self
    {
        $this->where(sprintf('e.site_id = 0x%s', $siteId));

        return $this;
    }

    public function appendZoneIdGroupBy(): self
    {
        $this->column('e.zone_id AS zone_id');
        $this->where('e.zone_id IS NOT NULL');
        $this->groupBy('e.zone_id');

        return $this;
    }

    public function appendSiteIdGroupBy(): self
    {
        $this->column('e.site_id AS site_id');
        $this->groupBy('e.site_id');
        $this->having('clicks>0');
        $this->having('views>0');
        $this->having('revenue>0');

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
