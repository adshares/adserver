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
use DateTimeInterface;
use function in_array;
use function sprintf;

class MySqlLiveStatsQueryBuilder extends MySqlQueryBuilder
{
    private const ALLOWED_TYPES = [
        StatsRepository::TYPE_VIEW,
        StatsRepository::TYPE_VIEW_UNIQUE,
        StatsRepository::TYPE_VIEW_ALL,
    ];

    public function __construct(string $type)
    {
        $this->selectBaseColumns($type);
        $this->join('network_impressions i', 'e.network_impression_id = i.id');
        $this->withoutRemovedSites();

        parent::__construct($type);
    }

    protected function isTypeAllowed(string $type): bool
    {
        return in_array($type, self::ALLOWED_TYPES, true);
    }

    protected function getTableName(): string
    {
        return 'network_cases e';
    }

    private function selectBaseColumns(string $type): void
    {
        switch ($type) {
            case StatsRepository::TYPE_VIEW:
                $this->column('SUM(IF(i.human_score >= 0.5, 1, 0)) AS c');
                break;
            case StatsRepository::TYPE_VIEW_ALL:
                $this->column('COUNT(1) AS c');
                break;
            case StatsRepository::TYPE_VIEW_UNIQUE:
                $this->column(
                    'COUNT(DISTINCT (IF(i.human_score >= 0.5, IFNULL(i.user_id, i.tracking_id), NULL))) AS c'
                );
                break;
        }
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

    public function setDateRange(DateTimeInterface $dateStart, DateTimeInterface $dateEnd): self
    {
        $this->where(
            sprintf(
                'e.created_at BETWEEN \'%s\' AND \'%s\'',
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
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                $this->groupBy('DAY(e.created_at)');
                $this->groupBy('HOUR(e.created_at)');
                break;
            case StatsRepository::RESOLUTION_DAY:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->column('DAY(e.created_at) AS d');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                $this->groupBy('DAY(e.created_at)');
                break;
            case StatsRepository::RESOLUTION_WEEK:
                $this->column('YEARWEEK(e.created_at, 3) as yw');
                $this->groupBy('YEARWEEK(e.created_at, 3)');
                break;
            case StatsRepository::RESOLUTION_MONTH:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                break;
            case StatsRepository::RESOLUTION_QUARTER:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('QUARTER(e.created_at) as q');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('QUARTER(e.created_at)');
                break;
            case StatsRepository::RESOLUTION_YEAR:
            default:
                $this->column('YEAR(e.created_at) AS y');
                $this->groupBy('YEAR(e.created_at)');
                break;
        }

        return $this;
    }

    public function appendSiteIdWhereClause(string $siteId): self
    {
        $this->where(sprintf('e.site_id = 0x%s', $siteId));

        return $this;
    }

    public function appendZoneIdWhereClause(string $zoneId): self
    {
        $this->where(sprintf('e.zone_id = 0x%s', $zoneId));

        return $this;
    }

    public function appendZoneIdGroupBy(): self
    {
        $this->column('e.zone_id AS zone_id');
        $this->groupBy('e.zone_id');

        return $this;
    }

    public function appendSiteIdGroupBy(): self
    {
        $this->column('e.site_id AS site_id');
        $this->groupBy('e.site_id');
        $this->having('views>0');

        return $this;
    }

    public function appendDomainGroupBy(): self
    {
        $this->column("IFNULL(e.domain, '') AS domain");
        $this->groupBy("IFNULL(e.domain, '')");

        return $this;
    }
}
