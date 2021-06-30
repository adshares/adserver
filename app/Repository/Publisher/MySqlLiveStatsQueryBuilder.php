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
        StatsRepository::TYPE_CLICK,
        StatsRepository::TYPE_CLICK_ALL,
        StatsRepository::TYPE_REVENUE_BY_CASE,
        StatsRepository::TYPE_REVENUE_BY_HOUR,
        StatsRepository::TYPE_STATS,
    ];

    public function __construct(string $type)
    {
        $this->selectBaseColumns($type);
        $this->addJoins($type);
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
            case StatsRepository::TYPE_CLICK:
                $this->column('SUM(IF(i.human_score >= 0.5, 1, 0)) AS c');
                break;
            case StatsRepository::TYPE_VIEW_ALL:
            case StatsRepository::TYPE_CLICK_ALL:
                $this->column('COUNT(1) AS c');
                break;
            case StatsRepository::TYPE_VIEW_UNIQUE:
                $this->column(
                    'COUNT(DISTINCT (IF(i.human_score >= 0.5, IFNULL(i.user_id, i.tracking_id), NULL))) AS c'
                );
                break;
            case StatsRepository::TYPE_REVENUE_BY_CASE:
            case StatsRepository::TYPE_REVENUE_BY_HOUR:
                $this->column('IFNULL(SUM(ncp.paid_amount_currency),0) AS c');
                break;
            case StatsRepository::TYPE_STATS:
                $this->selectBaseStatsColumns();
                break;
        }
    }

    private function selectBaseStatsColumns(): void
    {
        $this->column('SUM(IF(i.human_score >= 0.5 AND clicks.network_case_id IS NOT NULL, 1, 0)) AS clicks');
        $this->column('SUM(IF(i.human_score >= 0.5, 1, 0)) AS views');
        $this->column('IFNULL(SUM(ncp.paid_amount_currency), 0) AS revenue');
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
        $this->join('users', 'users.uuid = e.publisher_id');
        $this->column('users.id AS publisher_id');
        $this->column('users.email AS publisher_email');
        $this->groupBy('users.uuid');

        return $this;
    }

    public function setDateRange(DateTimeInterface $dateStart, DateTimeInterface $dateEnd): self
    {
        $dateColumn = StatsRepository::TYPE_REVENUE_BY_HOUR === $this->getType() ? 'ncp.pay_time' : 'e.created_at';

        $this->where(
            sprintf(
                $dateColumn . " BETWEEN '%s' AND '%s'",
                $this->convertDateTimeToMySqlDate($dateStart),
                $this->convertDateTimeToMySqlDate($dateEnd)
            )
        );

        return $this;
    }

    public function appendResolution(string $resolution): self
    {
        $dateColumn = StatsRepository::TYPE_REVENUE_BY_HOUR === $this->getType() ? 'ncp.pay_time' : 'e.created_at';

        switch ($resolution) {
            case StatsRepository::RESOLUTION_HOUR:
                $resolutionColumns = [
                    ['YEAR(%s)', 'y'],
                    ['MONTH(%s)', 'm'],
                    ['DAY(%s)', 'd'],
                    ['HOUR(%s)', 'h'],
                ];
                break;
            case StatsRepository::RESOLUTION_DAY:
                $resolutionColumns = [
                    ['YEAR(%s)', 'y'],
                    ['MONTH(%s)', 'm'],
                    ['DAY(%s)', 'd'],
                ];
                break;
            case StatsRepository::RESOLUTION_WEEK:
                $resolutionColumns = [
                    ['YEARWEEK(%s, 3)', 'yw'],
                ];
                break;
            case StatsRepository::RESOLUTION_MONTH:
                $resolutionColumns = [
                    ['YEAR(%s)', 'y'],
                    ['MONTH(%s)', 'm'],
                ];
                break;
            case StatsRepository::RESOLUTION_QUARTER:
                $resolutionColumns = [
                    ['YEAR(%s)', 'y'],
                    ['QUARTER(%s)', 'q'],
                ];
                break;
            case StatsRepository::RESOLUTION_YEAR:
            default:
                $resolutionColumns = [
                    ['YEAR(%s)', 'y'],
                ];
                break;
        }

        foreach ($resolutionColumns as $resolutionColumn) {
            $alias = $resolutionColumn[1];
            $column = sprintf('%s AS %s', sprintf($resolutionColumn[0], $dateColumn), $alias);
            $this->column($column);
            $this->groupBy($alias);
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
        $this->join('zones z', 'z.uuid = e.zone_id');
        $this->column('z.id AS zone_id');
        $this->column('z.name AS zone_name');
        $this->groupBy('z.id');

        return $this;
    }

    public function appendSiteIdGroupBy(): self
    {
        $this->column('s.id AS site_id');
        $this->column('s.name AS site_name');
        $this->groupBy('s.id');
        $this->having('clicks>0');
        $this->having('views>0');
        $this->having('revenue>0');

        return $this;
    }

    public function appendDomainGroupBy(): self
    {
        $this->column("IFNULL(e.domain, '') AS domain");
        $this->groupBy("IFNULL(e.domain, '')");

        return $this;
    }

    private function addJoins(string $type): void
    {
        if (
            in_array(
                $type,
                [
                StatsRepository::TYPE_VIEW,
                StatsRepository::TYPE_VIEW_UNIQUE,
                StatsRepository::TYPE_CLICK,
                ]
            )
        ) {
            $this->join('network_impressions i', 'e.network_impression_id = i.id');
        }

        if (
            in_array(
                $type,
                [
                StatsRepository::TYPE_CLICK,
                StatsRepository::TYPE_CLICK_ALL,
                ]
            )
        ) {
            $this->join('network_case_clicks clicks', 'e.id = clicks.network_case_id');
        }

        if (
            in_array(
                $type,
                [
                StatsRepository::TYPE_REVENUE_BY_CASE,
                StatsRepository::TYPE_REVENUE_BY_HOUR,
                ]
            )
        ) {
            $this->join('network_case_payments ncp', 'e.id = ncp.network_case_id');
        }

        if (StatsRepository::TYPE_STATS === $type) {
            $this->join('network_impressions i', 'e.network_impression_id = i.id', 'LEFT');
            $this->join('network_case_clicks clicks', 'e.id = clicks.network_case_id', 'LEFT');
            $this->join('network_case_payments ncp', 'e.id = ncp.network_case_id', 'LEFT');
        }
    }
}
