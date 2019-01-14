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
    private const BASE_QUERY = <<<SQL
SELECT count(e.created_at) AS c#resolutionCols
FROM event_logs e
       INNER JOIN
       (SELECT b.*
        FROM banners b
               INNER JOIN campaigns c 
                          ON b.campaign_id = c.id 
                            AND c.user_id = :advertiserId#campaignIdWhereClause#bannerIdWhereClause
       ) b
       ON
         e.banner_id = b.uuid
WHERE e.event_type = :eventType
  AND e.created_at BETWEEN :dateStart AND :dateEnd
#resolutionGroupBy
SQL;

    /**
     * @var string
     */
    private $query;

    public function __construct(string $type)
    {
        switch ($type) {
            case ChartInput::VIEW_TYPE:
                $str = "'".EventLog::TYPE_VIEW."'";
                $this->query = str_replace([':eventType'], [$str], self::BASE_QUERY);
                break;
            case ChartInput::CLICK_TYPE:
                $str = "'".EventLog::TYPE_CLICK."'";
                $this->query = str_replace([':eventType'], [$str], self::BASE_QUERY);
                break;
            default:
                $this->query = '';
                break;
        }
    }

    public function build(): string
    {
        return $this->query;
    }

    public function setAdvertiserId(int $advertiserId): self
    {
        $this->query = str_replace([':advertiserId'], [$advertiserId], $this->query);

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

    public function appendCampaignIdWhereClause(?int $campaignId = null): self
    {
        if ($campaignId === null) {
            $campaignIdWhereClause = '';
        } else {
            $campaignIdWhereClause = sprintf(' AND c.id = %d', $campaignId);
        }

        $this->query = str_replace(['#campaignIdWhereClause'], [$campaignIdWhereClause], $this->query);

        return $this;
    }

    public function appendBannerIdWhereClause(?int $bannerId = null): self
    {
        if ($bannerId === null) {
            $bannerIdWhereClause = '';
        } else {
            $bannerIdWhereClause = sprintf(' AND b.id = %d', $bannerId);
        }

        $this->query = str_replace(['#bannerIdWhereClause'], [$bannerIdWhereClause], $this->query);

        return $this;
    }
}
