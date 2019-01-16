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
use Adshares\Advertiser\Dto\ChartInput;
use DateTime;

class MySqlStatsQueryBuilder
{
    public const STATS_TYPE = 'stats';

    private const BASE_QUERY = <<<SQL
SELECT #selectedCols #resolutionCols
FROM event_logs e
WHERE e.created_at BETWEEN :dateStart AND :dateEnd
  AND e.advertiser_id = :advertiserId
  #bannerIdWhereClause
  #campaignIdWhereClause 
  #eventTypeWhereClause
#resolutionGroupBy
SQL;

    private const STATS_QUERY = <<<SQL
SELECT
  SUM(IF(e.event_type = 'click', 1, 0))                                                                   AS clicks,
  SUM(IF(e.event_type = 'view', 1, 0))                                                                    AS views,
  IFNULL(AVG(CASE WHEN (e.event_type <> 'view') THEN NULL WHEN (e.is_view_clicked) THEN 1 ELSE 0 END), 0) AS ctr,
  IFNULL(AVG(IF(e.event_type = 'click', e.event_value, NULL)), 0)                                         AS cpc,
  SUM(IF(e.event_type IN ('click', 'view'), e.event_value, 0))                                            AS cost,
  e.campaign_id                                                                                           AS cid
  #bannerIdCol
FROM event_logs e
WHERE e.created_at BETWEEN :dateStart AND :dateEnd
  AND e.advertiser_id = :advertiserId
  #campaignIdWhereClause
GROUP BY e.campaign_id #bannerIdGroupBy
SQL;

    /**
     * @var string
     */
    private $query;

    public function __construct(string $type)
    {
        $query = $this->chooseQuery($type);
        $query = $this->conditionallyReplaceSelectedColumns($type, $query);
        $query = $this->conditionallyReplaceEventType($type, $query);

        $this->query = $query;
    }

    private function chooseQuery(string $type): string
    {
        switch ($type) {
            case ChartInput::VIEW_TYPE:
            case ChartInput::CLICK_TYPE:
            case ChartInput::CPC_TYPE:
            case ChartInput::CPM_TYPE:
            case ChartInput::SUM_TYPE:
            case ChartInput::CTR_TYPE:
                $query = self::BASE_QUERY;
                break;
            case self::STATS_TYPE:
                $query = self::STATS_QUERY;
                break;
            default:
                $query = '';
                break;
        }

        return $query;
    }

    private function conditionallyReplaceSelectedColumns(string $type, string $query): string
    {
        switch ($type) {
            case ChartInput::VIEW_TYPE:
            case ChartInput::CLICK_TYPE:
                $query = str_replace('#selectedCols', 'COUNT(e.created_at) AS c', $query);
                break;
            case ChartInput::CPC_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(AVG(e.event_value), 0) AS c', $query);
                break;
            case ChartInput::CPM_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(AVG(e.event_value), 0)*1000 AS c', $query);
                break;
            case ChartInput::SUM_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(SUM(e.event_value), 0) AS c', $query);
                break;
            case ChartInput::CTR_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(AVG(IF(e.is_view_clicked, 1, 0)), 0) AS c', $query);
                break;
            default:
                break;
        }

        return $query;
    }

    private function conditionallyReplaceEventType(string $type, string $query): string
    {
        switch ($type) {
            case ChartInput::VIEW_TYPE:
            case ChartInput::CPM_TYPE:
            case ChartInput::CTR_TYPE:
                $str = sprintf("AND e.event_type = '%s'", EventLog::TYPE_VIEW);
                $query = str_replace('#eventTypeWhereClause', $str, $query);
                break;
            case ChartInput::CLICK_TYPE:
            case ChartInput::CPC_TYPE:
                $str = sprintf("AND e.event_type = '%s'", EventLog::TYPE_CLICK);
                $query = str_replace('#eventTypeWhereClause', $str, $query);
                break;
            case ChartInput::SUM_TYPE:
                $query = str_replace('#eventTypeWhereClause', '', $query);
                break;
            default:
                break;
        }

        return $query;
    }

    public function build(): string
    {
        return $this->query;
    }

    public function setAdvertiserId(string $advertiserId): self
    {
        $this->query = str_replace([':advertiserId'], ['0x'.$advertiserId], $this->query);

        return $this;
    }

    public function setDateRange(DateTime $dateStart, DateTime $dateEnd): self
    {
        $str = "'".$this->convertDateTimeToMySqlDate($dateStart)."'";
        $this->query = str_replace([':dateStart'], [$str], $this->query);

        $str = "'".$this->convertDateTimeToMySqlDate($dateEnd)."'";
        $this->query = str_replace([':dateEnd'], [$str], $this->query);

        return $this;
    }

    private function convertDateTimeToMySqlDate(DateTime $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    public function appendResolution(string $resolution): self
    {
        switch ($resolution) {
            case ChartInput::HOUR_RESOLUTION:
                $cols =
                    ', YEAR(e.created_at) AS y, MONTH(e.created_at) as m, '
                    .'DAY(e.created_at) AS d, HOUR(e.created_at) AS h';
                $groupBy = 'GROUP BY YEAR(e.created_at), MONTH(e.created_at), DAY(e.created_at), HOUR(e.created_at)';
                break;
            case ChartInput::DAY_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, MONTH(e.created_at) as m, DAY(e.created_at) AS d';
                $groupBy = 'GROUP BY YEAR(e.created_at), MONTH(e.created_at), DAY(e.created_at)';
                break;
            case ChartInput::WEEK_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, WEEK(e.created_at) as w';
                $groupBy = 'GROUP BY YEAR(e.created_at), WEEK(e.created_at)';
                break;
            case ChartInput::MONTH_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, MONTH(e.created_at) as m';
                $groupBy = 'GROUP BY YEAR(e.created_at), MONTH(e.created_at)';
                break;
            case ChartInput::QUARTER_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, QUARTER(e.created_at) as q';
                $groupBy = 'GROUP BY YEAR(e.created_at), QUARTER(e.created_at)';
                break;
            case ChartInput::YEAR_RESOLUTION:
            default:
                $cols = ', YEAR(e.created_at) AS y';
                $groupBy = 'GROUP BY YEAR(e.created_at)';
                break;
        }

        $this->query = str_replace(['#resolutionCols', '#resolutionGroupBy'], [$cols, $groupBy], $this->query);

        return $this;
    }

    public function appendCampaignIdWhereClause(?string $campaignId = null): self
    {
        if ($campaignId === null) {
            $campaignIdWhereClause = '';
        } else {
            $campaignIdWhereClause = sprintf('AND e.campaign_id = 0x%s', $campaignId);
        }

        $this->query = str_replace(['#campaignIdWhereClause'], [$campaignIdWhereClause], $this->query);

        return $this;
    }

    public function appendBannerIdWhereClause(?int $bannerId = null): self
    {
        if ($bannerId === null) {
            $bannerIdWhereClause = '';
        } else {
            $bannerIdWhereClause = sprintf('AND e.banner_id = 0x%s', $bannerId);
        }

        $this->query = str_replace(['#bannerIdWhereClause'], [$bannerIdWhereClause], $this->query);

        return $this;
    }

    public function appendBannerIdGroupBy(?int $campaignId = null): self
    {
        if ($campaignId === null) {
            $bannerIdCol = '';
            $bannerIdGroupBy = '';
        } else {
            $bannerIdCol = ', e.banner_id AS bid';
            $bannerIdGroupBy = ', e.banner_id';
        }
        $this->query =
            str_replace(['#bannerIdCol', '#bannerIdGroupBy'], [$bannerIdCol, $bannerIdGroupBy], $this->query);

        return $this;
    }
}
