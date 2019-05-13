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

declare(strict_types = 1);

namespace Adshares\Adserver\Repository\Advertiser;

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use Adshares\Advertiser\Repository\StatsRepository;
use DateTime;
use function in_array;
use function sprintf;

class MySqlStatsQueryBuilder extends MySqlQueryBuilder
{
    protected const TABLE_NAME = 'event_logs e';

    private const ALLOWED_TYPES = [
        StatsRepository::TYPE_VIEW,
        StatsRepository::TYPE_VIEW_UNIQUE,
        StatsRepository::TYPE_VIEW_ALL,
        StatsRepository::TYPE_VIEW_INVALID_RATE,
        StatsRepository::TYPE_CLICK,
        StatsRepository::TYPE_CLICK_ALL,
        StatsRepository::TYPE_CLICK_INVALID_RATE,
        StatsRepository::TYPE_SUM,
        StatsRepository::TYPE_CTR,
        StatsRepository::TYPE_STATS,
        StatsRepository::TYPE_STATS_REPORT,
    ];

    public function __construct(string $type)
    {
        $this->selectBaseColumns($type);
        $this->appendEventType($type);
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
        switch ($type) {
            case StatsRepository::TYPE_VIEW:
            case StatsRepository::TYPE_VIEW_ALL:
            case StatsRepository::TYPE_CLICK:
            case StatsRepository::TYPE_CLICK_ALL:
                $this->column('COUNT(1) AS c');
                break;
            case StatsRepository::TYPE_VIEW_UNIQUE:
                $this->column('COUNT(DISTINCT e.user_id, e.campaign_id) AS c');
                break;
            case StatsRepository::TYPE_VIEW_INVALID_RATE:
            case StatsRepository::TYPE_CLICK_INVALID_RATE:
                $this->column('COALESCE(AVG(IF(e.event_value_currency IS NULL OR e.reason <> 0, 1, 0)), 0) AS c');
                break;
            case StatsRepository::TYPE_SUM:
                $this->column('COALESCE(SUM(e.event_value_currency), 0) AS c');
                break;
            case StatsRepository::TYPE_CTR:
                $this->column('COALESCE(AVG(IF(e.is_view_clicked, 1, 0)), 0) AS c');
                break;
            case StatsRepository::TYPE_STATS:
                $this->selectBaseStatsColumns();
                break;
            case StatsRepository::TYPE_STATS_REPORT:
                $this->selectBaseStatsReportColumns();
                break;
        }
    }

    private function appendEventType(string $type): void
    {
        switch ($type) {
            case StatsRepository::TYPE_VIEW:
            case StatsRepository::TYPE_VIEW_UNIQUE:
            case StatsRepository::TYPE_CTR:
                $this->where(sprintf("e.event_type = '%s'", EventLog::TYPE_VIEW));
                $this->where('e.event_value_currency IS NOT NULL');
                $this->where('e.reason = 0');
                break;
            case StatsRepository::TYPE_VIEW_ALL:
            case StatsRepository::TYPE_VIEW_INVALID_RATE:
                $this->where(sprintf("e.event_type = '%s'", EventLog::TYPE_VIEW));
                break;
            case StatsRepository::TYPE_CLICK:
                $this->where(sprintf("e.event_type = '%s'", EventLog::TYPE_VIEW));
                $this->where('e.is_view_clicked = 1');
                $this->where('e.event_value_currency IS NOT NULL');
                $this->where('e.reason = 0');
                break;
            case StatsRepository::TYPE_CLICK_ALL:
            case StatsRepository::TYPE_CLICK_INVALID_RATE:
                $this->where(sprintf("e.event_type = '%s'", EventLog::TYPE_VIEW));
                $this->where('e.is_view_clicked = 1');
                break;
        }
    }

    private function withoutRemovedCampaigns(): void
    {
        $this->join('campaigns c', 'c.uuid = e.campaign_id');
        $this->where('c.deleted_at is null');
    }

    private function selectBaseStatsColumns(): void
    {
        $filterEventValid = 'AND e.event_value_currency IS NOT NULL AND e.reason = 0';

        $this->column(
            sprintf(
                "SUM(IF(e.event_type = '%s' AND e.is_view_clicked = 1 %s, 1, 0)) AS clicks",
                EventLog::TYPE_VIEW,
                $filterEventValid
            )
        );
        $this->column(
            sprintf("SUM(IF(e.event_type = '%s' %s, 1, 0)) AS views", EventLog::TYPE_VIEW, $filterEventValid)
        );
        $this->column(
            sprintf(
                "SUM(IF(e.event_type IN ('%s', '%s') %s, e.event_value_currency, 0)) AS cost",
                EventLog::TYPE_CLICK,
                EventLog::TYPE_VIEW,
                $filterEventValid
            )
        );
    }

    private function selectBaseStatsReportColumns(): void
    {
        $this->selectBaseStatsColumns();

        $this->column(
            sprintf(
                "SUM(IF(e.event_type = '%s' AND e.is_view_clicked = 1, 1, 0)) AS clicksAll",
                EventLog::TYPE_VIEW
            )
        );
        $this->column(
            sprintf("SUM(IF(e.event_type = '%s', 1, 0)) AS viewsAll", EventLog::TYPE_VIEW)
        );
        $this->column(
            sprintf(
                "COUNT(DISTINCT(CASE WHEN e.event_type = '%s' AND e.event_value_currency IS NOT NULL AND e.reason = 0"
                .' THEN e.user_id END)) AS viewsUnique',
                EventLog::TYPE_VIEW
            )
        );
    }

    public function setAdvertiserId(string $advertiserId): self
    {
        $this->where(sprintf('e.advertiser_id = 0x%s', $advertiserId));

        return $this;
    }

    public function setDateRange(DateTime $dateStart, DateTime $dateEnd): self
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

    private function convertDateTimeToMySqlDate(DateTime $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
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
        $this->column('e.banner_id AS banner_id');
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
