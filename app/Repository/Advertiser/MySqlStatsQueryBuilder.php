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
use DateTime;
use RuntimeException;

class MySqlStatsQueryBuilder
{
    public const VIEW_TYPE = 'view';

    public const CLICK_TYPE = 'click';

    public const CPC_TYPE = 'cpc';

    public const CPM_TYPE = 'cpm';

    public const SUM_TYPE = 'sum';

    public const CTR_TYPE = 'ctr';

    public const STATS_TYPE = 'stats';

    public const HOUR_RESOLUTION = 'hour';

    public const DAY_RESOLUTION = 'day';

    public const WEEK_RESOLUTION = 'week';

    public const MONTH_RESOLUTION = 'month';

    public const QUARTER_RESOLUTION = 'quarter';

    public const YEAR_RESOLUTION = 'year';

    private const ALLOWED_TYPES = [
        self::VIEW_TYPE,
        self::CLICK_TYPE,
        self::CPC_TYPE,
        self::CPM_TYPE,
        self::SUM_TYPE,
        self::CTR_TYPE,
        self::STATS_TYPE,
    ];

    private const CHART_QUERY = <<<SQL
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
        if (!self::isTypeAllowed($type)) {
            throw new RuntimeException(sprintf('Unsupported query type: %s', $type));
        }

        if ($type === self::STATS_TYPE) {
            return self::STATS_QUERY;
        }

        return self::CHART_QUERY;
    }

    private static function isTypeAllowed(string $type): bool
    {
        return in_array($type, self::ALLOWED_TYPES, true);
    }

    private function conditionallyReplaceSelectedColumns(string $type, string $query): string
    {
        switch ($type) {
            case self::VIEW_TYPE:
            case self::CLICK_TYPE:
                $query = str_replace('#selectedCols', 'COUNT(e.created_at) AS c', $query);
                break;
            case self::CPC_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(AVG(e.event_value), 0) AS c', $query);
                break;
            case self::CPM_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(AVG(e.event_value), 0)*1000 AS c', $query);
                break;
            case self::SUM_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(SUM(e.event_value), 0) AS c', $query);
                break;
            case self::CTR_TYPE:
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
            case self::VIEW_TYPE:
            case self::CPM_TYPE:
            case self::CTR_TYPE:
                $str = sprintf("AND e.event_type = '%s'", EventLog::TYPE_VIEW);
                $query = str_replace('#eventTypeWhereClause', $str, $query);
                break;
            case self::CLICK_TYPE:
            case self::CPC_TYPE:
                $str = sprintf("AND e.event_type = '%s'", EventLog::TYPE_CLICK);
                $query = str_replace('#eventTypeWhereClause', $str, $query);
                break;
            case self::SUM_TYPE:
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
            case self::HOUR_RESOLUTION:
                $cols =
                    ', YEAR(e.created_at) AS y, MONTH(e.created_at) as m, '
                    .'DAY(e.created_at) AS d, HOUR(e.created_at) AS h';
                $groupBy = 'GROUP BY YEAR(e.created_at), MONTH(e.created_at), DAY(e.created_at), HOUR(e.created_at)';
                break;
            case self::DAY_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, MONTH(e.created_at) as m, DAY(e.created_at) AS d';
                $groupBy = 'GROUP BY YEAR(e.created_at), MONTH(e.created_at), DAY(e.created_at)';
                break;
            case self::WEEK_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, WEEK(e.created_at) as w';
                $groupBy = 'GROUP BY YEAR(e.created_at), WEEK(e.created_at)';
                break;
            case self::MONTH_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, MONTH(e.created_at) as m';
                $groupBy = 'GROUP BY YEAR(e.created_at), MONTH(e.created_at)';
                break;
            case self::QUARTER_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, QUARTER(e.created_at) as q';
                $groupBy = 'GROUP BY YEAR(e.created_at), QUARTER(e.created_at)';
                break;
            case self::YEAR_RESOLUTION:
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

    public function appendBannerIdWhereClause(?string $bannerId = null): self
    {
        if ($bannerId === null) {
            $bannerIdWhereClause = '';
        } else {
            $bannerIdWhereClause = sprintf('AND e.banner_id = 0x%s', $bannerId);
        }

        $this->query = str_replace(['#bannerIdWhereClause'], [$bannerIdWhereClause], $this->query);

        return $this;
    }

    public function appendBannerIdGroupBy(?string $campaignId = null): self
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
