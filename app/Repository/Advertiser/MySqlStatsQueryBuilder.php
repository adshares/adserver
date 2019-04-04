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
        StatsRepository::VIEW_TYPE,
        StatsRepository::CLICK_TYPE,
        StatsRepository::CPC_TYPE,
        StatsRepository::CPM_TYPE,
        StatsRepository::SUM_TYPE,
        StatsRepository::CTR_TYPE,
        StatsRepository::STATS_TYPE,
        StatsRepository::STATS_SUM_TYPE,
    ];

    public function __construct(string $type)
    {
        $this->selectBaseColumns($type);
        $this->appendEventType($type);

        if ($type === StatsRepository::STATS_TYPE || $type === StatsRepository::STATS_SUM_TYPE) {
            $this->selectBaseStatsColumns();
        }

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
            case StatsRepository::VIEW_TYPE:
            case StatsRepository::CLICK_TYPE:
                $this->column('COUNT(e.created_at) AS c');
                break;
            case StatsRepository::CPC_TYPE:
                $this->column('COALESCE(ROUND(AVG(e.event_value)), 0) AS c');
                break;
            case StatsRepository::CPM_TYPE:
                $this->column('COALESCE(ROUND(AVG(e.event_value)), 0)*1000 AS c');
                break;
            case StatsRepository::SUM_TYPE:
                $this->column('COALESCE(SUM(e.event_value), 0) AS c');
                break;
            case StatsRepository::CTR_TYPE:
                $this->column('COALESCE(AVG(IF(e.is_view_clicked, 1, 0)), 0) AS c');
                break;
        }
    }

    private function appendEventType(string $type): void
    {
        switch ($type) {
            case StatsRepository::VIEW_TYPE:
            case StatsRepository::CPM_TYPE:
            case StatsRepository::CTR_TYPE:
                $this->where(sprintf("e.event_type = '%s'", EventLog::TYPE_VIEW));
                break;
            case StatsRepository::CLICK_TYPE:
                $this->where(sprintf("e.event_type = '%s'", EventLog::TYPE_VIEW));
                $this->where(sprintf('e.is_view_clicked = %d', 1));
                break;
            case StatsRepository::CPC_TYPE:
                $this->where(sprintf("e.event_type = '%s'", EventLog::TYPE_CLICK));
                break;
        }
    }

    private function selectBaseStatsColumns(): void
    {
        $this->column('SUM(IF(e.event_type = \'view\' AND e.is_view_clicked = 1, 1, 0)) AS clicks');
        $this->column('SUM(IF(e.event_type = \'view\', 1, 0)) AS views');
        $this->column(
            'IFNULL(AVG(CASE '
            .'WHEN (e.event_type <> \'view\') THEN NULL '
            .'WHEN (e.is_view_clicked = 1) THEN 1 ELSE 0 END), 0) AS ctr'
        );
        $this->column('IFNULL(ROUND(AVG(IF(e.event_type = \'click\', e.event_value, NULL))), 0) AS cpc');
        $this->column('IFNULL(ROUND(AVG(IF(e.event_type = \'view\', e.event_value, NULL))), 0)*1000 AS cpm');
        $this->column('SUM(IF(e.event_type IN (\'click\', \'view\'), e.event_value, 0)) AS cost');
    }

    public function setAdvertiserId(string $advertiserId): self
    {
        $this->where(sprintf('e.advertiser_id = 0x%s', $advertiserId));

        return $this;
    }

    public function setDateRange(DateTime $dateStart, DateTime $dateEnd): self
    {
        $this->where(sprintf(
            'e.created_at BETWEEN \'%s\' AND \'%s\'',
            $this->convertDateTimeToMySqlDate($dateStart),
            $this->convertDateTimeToMySqlDate($dateEnd)
        ));

        return $this;
    }

    private function convertDateTimeToMySqlDate(DateTime $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    public function appendResolution(string $resolution): self
    {
        switch ($resolution) {
            case StatsRepository::HOUR_RESOLUTION:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->column('DAY(e.created_at) AS d');
                $this->column('HOUR(e.created_at) AS h');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                $this->groupBy('DAY(e.created_at)');
                $this->groupBy('HOUR(e.created_at)');
                break;
            case StatsRepository::DAY_RESOLUTION:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->column('DAY(e.created_at) AS d');

                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                $this->groupBy('DAY(e.created_at)');
                break;
            case StatsRepository::WEEK_RESOLUTION:
                $this->column('YEARWEEK(e.created_at, 3) as yw');
                $this->groupBy('YEARWEEK(e.created_at, 3)');
                break;
            case StatsRepository::MONTH_RESOLUTION:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                break;
            case StatsRepository::QUARTER_RESOLUTION:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('QUARTER(e.created_at) as q');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('QUARTER(e.created_at)');
                break;
            case StatsRepository::YEAR_RESOLUTION:
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
        $this->having('ctr>0');
        $this->having('cpc>0');
        $this->having('cpm>0');
        $this->having('cost>0');

        return $this;
    }

    public function appendDomainGroupBy(): self
    {
        $this->column('e.domain AS domain');
        $this->groupBy('e.domain');

        return $this;
    }
}
