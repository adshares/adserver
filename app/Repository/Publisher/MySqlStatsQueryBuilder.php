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

namespace Adshares\Adserver\Repository\Publisher;

use Adshares\Adserver\Models\EventLog;
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;
use RuntimeException;

class MySqlStatsQueryBuilder
{
    private const ALLOWED_TYPES = [
        StatsRepository::VIEW_TYPE,
        StatsRepository::CLICK_TYPE,
        StatsRepository::RPC_TYPE,
        StatsRepository::RPM_TYPE,
        StatsRepository::SUM_TYPE,
        StatsRepository::CTR_TYPE,
        StatsRepository::STATS_TYPE,
        StatsRepository::STATS_SUM_TYPE,
    ];

    private const CHART_QUERY = <<<SQL
SELECT #selectedCols #resolutionCols
FROM network_event_logs e
WHERE e.created_at BETWEEN :dateStart AND :dateEnd
  AND e.publisher_id = :publisherId
  #zoneIdWhereClause
  #siteIdWhereClause 
  #eventTypeWhereClause
#resolutionGroupBy
SQL;

    private const STATS_QUERY = <<<SQL
SELECT
  SUM(IF(e.event_type = 'click', 1, 0))                                                                   AS clicks,
  SUM(IF(e.event_type = 'view', 1, 0))                                                                    AS views,
  IFNULL(AVG(CASE WHEN (e.event_type <> 'view') THEN NULL WHEN (e.is_view_clicked) THEN 1 ELSE 0 END), 0) AS ctr,
  IFNULL(AVG(IF(e.event_type = 'click', e.paid_amount, NULL)), 0)                                         AS rpc,
  IFNULL(AVG(IF(e.event_type = 'view', e.paid_amount, NULL)), 0)*1000                                     AS rpm,
  SUM(IF(e.event_type IN ('click', 'view'), e.paid_amount, 0))                                            AS revenue
  #siteIdCol
  #zoneIdCol
FROM network_event_logs e
WHERE e.created_at BETWEEN :dateStart AND :dateEnd
  AND e.publisher_id = :publisherId
  #siteIdWhereClause
#siteIdGroupBy #zoneIdGroupBy
#having
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

        if ($type === StatsRepository::STATS_TYPE || $type === StatsRepository::STATS_SUM_TYPE) {
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
            case StatsRepository::VIEW_TYPE:
            case StatsRepository::CLICK_TYPE:
                $query = str_replace('#selectedCols', 'COUNT(e.created_at) AS c', $query);
                break;
            case StatsRepository::RPC_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(AVG(e.paid_amount), 0) AS c', $query);
                break;
            case StatsRepository::RPM_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(AVG(e.paid_amount), 0)*1000 AS c', $query);
                break;
            case StatsRepository::SUM_TYPE:
                $query = str_replace('#selectedCols', 'COALESCE(SUM(e.paid_amount), 0) AS c', $query);
                break;
            case StatsRepository::CTR_TYPE:
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
            case StatsRepository::VIEW_TYPE:
            case StatsRepository::RPM_TYPE:
            case StatsRepository::CTR_TYPE:
                $str = sprintf("AND e.event_type = '%s'", EventLog::TYPE_VIEW);
                $query = str_replace('#eventTypeWhereClause', $str, $query);
                break;
            case StatsRepository::CLICK_TYPE:
            case StatsRepository::RPC_TYPE:
                $str = sprintf("AND e.event_type = '%s'", EventLog::TYPE_CLICK);
                $query = str_replace('#eventTypeWhereClause', $str, $query);
                break;
            case StatsRepository::SUM_TYPE:
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

    public function setPublisherId(string $publisherId): self
    {
        $this->query = str_replace([':publisherId'], ['0x'.$publisherId], $this->query);

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
            case StatsRepository::HOUR_RESOLUTION:
                $cols =
                    ', YEAR(e.created_at) AS y, MONTH(e.created_at) as m, '
                    .'DAY(e.created_at) AS d, HOUR(e.created_at) AS h';
                $groupBy = 'GROUP BY YEAR(e.created_at), MONTH(e.created_at), DAY(e.created_at), HOUR(e.created_at)';
                break;
            case StatsRepository::DAY_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, MONTH(e.created_at) as m, DAY(e.created_at) AS d';
                $groupBy = 'GROUP BY YEAR(e.created_at), MONTH(e.created_at), DAY(e.created_at)';
                break;
            case StatsRepository::WEEK_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, WEEK(e.created_at, 3) as w';
                $groupBy = 'GROUP BY YEAR(e.created_at), WEEK(e.created_at, 3)';
                break;
            case StatsRepository::MONTH_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, MONTH(e.created_at) as m';
                $groupBy = 'GROUP BY YEAR(e.created_at), MONTH(e.created_at)';
                break;
            case StatsRepository::QUARTER_RESOLUTION:
                $cols = ', YEAR(e.created_at) AS y, QUARTER(e.created_at) as q';
                $groupBy = 'GROUP BY YEAR(e.created_at), QUARTER(e.created_at)';
                break;
            case StatsRepository::YEAR_RESOLUTION:
            default:
                $cols = ', YEAR(e.created_at) AS y';
                $groupBy = 'GROUP BY YEAR(e.created_at)';
                break;
        }

        $this->query = str_replace(['#resolutionCols', '#resolutionGroupBy'], [$cols, $groupBy], $this->query);

        return $this;
    }

    public function appendSiteIdWhereClause(?string $siteId = null): self
    {
        if ($siteId === null) {
            $siteIdWhereClause = '';
        } else {
            $siteIdWhereClause = sprintf('AND e.site_id = 0x%s', $siteId);
        }

        $this->query = str_replace(['#siteIdWhereClause'], [$siteIdWhereClause], $this->query);

        return $this;
    }

    public function appendZoneIdWhereClause(?string $zoneId = null): self
    {
        if ($zoneId === null) {
            $zoneIdWhereClause = '';
        } else {
            $zoneIdWhereClause = sprintf('AND e.zone_id = 0x%s', $zoneId);
        }

        $this->query = str_replace(['#zoneIdWhereClause'], [$zoneIdWhereClause], $this->query);

        return $this;
    }

    public function appendZoneIdGroupBy(?string $siteId = null): self
    {
        if ($siteId === null) {
            $zoneIdCol = '';
            $zoneIdGroupBy = '';
        } else {
            $zoneIdCol = ', e.zone_id AS zone_id';
            $zoneIdGroupBy = ', e.zone_id';
        }
        $this->query = str_replace(['#zoneIdCol', '#zoneIdGroupBy'], [$zoneIdCol, $zoneIdGroupBy], $this->query);

        return $this;
    }

    public function appendSiteIdGroupBy(bool $appendSiteIdGroupBy): self
    {
        if ($appendSiteIdGroupBy) {
            $siteIdCol = ', e.site_id AS site_id';
            $siteIdGroupBy = 'GROUP BY e.site_id';
            $having = 'HAVING clicks>0 OR views>0 OR ctr>0 OR rpc>0 OR rpm>0 OR revenue>0';
        } else {
            $siteIdCol = '';
            $siteIdGroupBy = '';
            $having = '';
        }
        $this->query =
            str_replace(
                ['#siteIdCol', '#siteIdGroupBy', '#having'],
                [$siteIdCol, $siteIdGroupBy, $having],
                $this->query
            );

        return $this;
    }
}
