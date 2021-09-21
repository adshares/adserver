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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Utilities\DomainReader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    private const MIN_DAY_VIEWS = 1000;

    private const MAX_CHANGE = 9.999;

    public function browse(Request $request): LengthAwarePaginator
    {
        $query = $request->get('q');

        if ($query) {
            $domains =
                DB::select(
                    'SELECT DISTINCT user_id from sites WHERE deleted_at IS NULL AND domain LIKE ? LIMIT 100',
                    ['%' . $query . '%']
                );
            $campaigns =
                DB::select(
                    'SELECT DISTINCT user_id from campaigns WHERE deleted_at IS NULL AND landing_url LIKE ? LIMIT 100',
                    ['%' . $query . '%']
                );

            $ids = array_unique(
                array_merge(
                    array_map(
                        function ($row) {
                            return $row->user_id;
                        },
                        $domains
                    ),
                    array_map(
                        function ($row) {
                            return $row->user_id;
                        },
                        $campaigns
                    )
                )
            );

            $builder = User::where('email', 'LIKE', '%' . $query . '%')->orWhereIn('id', $ids);
        } else {
            $builder = User::query();
        }

        foreach ((array)$request->get('f', []) as $filter) {
            switch ($filter) {
                case 'email-unconfirmed':
                    $builder->whereNull('email_confirmed_at');
                    break;
                case 'admin-unconfirmed':
                    $builder->whereNull('admin_confirmed_at');
                    break;
                case 'advertisers':
                    $builder->where('is_advertiser', true);
                    break;
                case 'publishers':
                    $builder->where('is_publisher', true);
                    break;
            }
        }

        $direction = $request->get('d') === 'desc' ? 'desc' : 'asc';
        switch ($request->get('o')) {
            case 'email':
                $builder->orderBy('email', $direction);
                break;
        }
        $builder->orderBy('id');

        return $builder->paginate();
    }

    public function advertisers(Request $request): JsonResponse
    {
        if (strtolower((string)$request->get('g')) === 'user') {
            $emailColumn = 'u.email';
            $landingUrlColumn = 'GROUP_CONCAT(DISTINCT c.landing_url SEPARATOR ", ")';
            $groupBy = $emailColumn;
        } else {
            $emailColumn = 'GROUP_CONCAT(DISTINCT u.email SEPARATOR ", ")';
            $landingUrlColumn = 'c.landing_url';
            $groupBy = $landingUrlColumn;
        }

        $viewsLimit = (int)$request->get('l', self::MIN_DAY_VIEWS);
        if (strtolower((string)$request->get('i')) === 'hour') {
            $interval = 1;
            $unit = 'HOUR';
            $viewsLimit /= 24;
        } elseif (strtolower((string)$request->get('i')) === 'day') {
            $interval = 1;
            $unit = 'DAY';
        } else {
            $interval = 7;
            $unit = 'DAY';
            $viewsLimit *= 7;
        }

        $query = '%' . $request->get('q', '') . '%';

        $advertisers =
            DB::select(
                $q = sprintf(
                    'SELECT 
                    GROUP_CONCAT(DISTINCT u.id SEPARATOR ",") AS user_ids,
                    %s AS email,
                    %s AS landing_url,
                    GROUP_CONCAT(DISTINCT c.name SEPARATOR ", ") AS name,
                    SUM(IFNULL(lc.views, 0)) AS current_views,
                    SUM(IFNULL(lc.views_all, 0)) AS current_views_all,
                    SUM(IFNULL(lc.views_unique, 0)) AS current_views_unique,
                    SUM(IFNULL(lc.clicks, 0)) AS current_clicks,
                    SUM(IFNULL(lc.cost, 0)) AS current_cost,
                    SUM(IFNULL(lp.views, 0)) AS last_views,
                    SUM(IFNULL(lp.views_all, 0)) AS last_views_all,
                    SUM(IFNULL(lp.views_unique, 0)) AS last_views_unique,
                    SUM(IFNULL(lp.clicks, 0)) AS last_clicks,
                    SUM(IFNULL(lp.cost, 0)) AS last_cost
                FROM campaigns c
                LEFT JOIN users u ON u.id = c.user_id
                LEFT JOIN (
                    SELECT
                        l.campaign_id,
                        SUM(l.cost_payment) AS cost,
                        SUM(l.views_all) AS views_all,
                        SUM(l.views) AS views,
                        SUM(l.views_unique) AS views_unique,
                        SUM(l.clicks) AS clicks
                    FROM event_logs_hourly_stats l
                    WHERE l.hour_timestamp BETWEEN NOW() - INTERVAL %d %s - INTERVAL 2 HOUR
                        AND NOW() - INTERVAL 2 HOUR
                    GROUP BY l.campaign_id
                ) lc ON lc.campaign_id = c.uuid
                LEFT JOIN (
                    SELECT
                        l.campaign_id,
                        SUM(l.cost_payment) AS cost,
                        SUM(l.views_all) AS views_all,
                        SUM(l.views) AS views,
                        SUM(l.views_unique) AS views_unique,
                        SUM(l.clicks) AS clicks
                    FROM event_logs_hourly_stats l
                    WHERE l.hour_timestamp BETWEEN NOW() - INTERVAL %d %s - INTERVAL 2 HOUR
                        AND NOW() - INTERVAL %d %s - INTERVAL 2 HOUR
                    GROUP BY l.campaign_id
                ) lp ON lp.campaign_id = c.uuid
                WHERE c.deleted_at IS NULL AND (c.landing_url LIKE ? OR u.email LIKE ?)
                GROUP BY %s
                HAVING current_views >= ? OR last_views >= ?
                ',
                    $emailColumn,
                    $landingUrlColumn,
                    $interval,
                    $unit,
                    $interval * 2,
                    $unit,
                    $interval,
                    $unit,
                    $groupBy
                ),
                [
                    $query,
                    $query,
                    $viewsLimit * $interval,
                    $viewsLimit * $interval,
                ]
            );

        $data = array_map(
            function ($row) {
                $currentCtr = $row->current_clicks / max(1, $row->current_views);
                $lastCtr = $row->last_clicks / max(1, $row->last_views);
                $currentCpm = (int)($row->current_cost / max(1, $row->current_views) * 1000);
                $lastCpm = (int)($row->last_cost / max(1, $row->last_views) * 1000);
                $currentCpc = (int)($row->current_cost / max(1, $row->current_clicks));
                $lastCpc = (int)($row->last_cost / max(1, $row->last_clicks));

                $domain = join(
                    ', ',
                    array_unique(
                        array_map(
                            function ($url) {
                                return DomainReader::domain($url);
                            },
                            explode(', ', $row->landing_url)
                        )
                    )
                );

                return [
                    'user_ids' => $this->extractUserIds($row->user_ids),
                    'email' => $row->email,
                    'domain' => $domain,
                    'name' => $row->name,
                    'views' => (int)$row->current_views,
                    'viewsDiff' => $row->current_views - $row->last_views,
                    'viewsChange' => min(
                        self::MAX_CHANGE,
                        ($row->current_views - $row->last_views) / ($row->last_views == 0 ? 1 : $row->last_views)
                    ),
                    'viewsUnique' => (int)$row->current_views_unique,
                    'viewsUniqueDiff' => $row->current_views_unique - $row->last_views_unique,
                    'viewsUniqueChange' => min(
                        self::MAX_CHANGE,
                        ($row->current_views_unique - $row->last_views_unique)
                        / ($row->last_views_unique == 0 ? 1 : $row->last_views_unique)
                    ),
                    'clicks' => (int)$row->current_clicks,
                    'clicksDiff' => $row->current_clicks - $row->last_clicks,
                    'clicksChange' => min(
                        self::MAX_CHANGE,
                        ($row->current_clicks - $row->last_clicks) / ($row->last_clicks == 0 ? 1 : $row->last_clicks)
                    ),
                    'ctr' => $currentCtr,
                    'ctrDiff' => $currentCtr - $lastCtr,
                    'ctrChange' => min(self::MAX_CHANGE, ($currentCtr - $lastCtr) / ($lastCtr == 0 ? 1 : $lastCtr)),
                    'cost' => (int)$row->current_cost,
                    'costDiff' => $row->current_cost - $row->last_cost,
                    'costChange' => min(
                        self::MAX_CHANGE,
                        ($row->current_cost - $row->last_cost) / ($row->last_cost == 0 ? 1
                            : $row->last_cost)
                    ),
                    'cpm' => $currentCpm,
                    'cpmDiff' => $currentCpm - $lastCpm,
                    'cpmChange' => min(self::MAX_CHANGE, ($currentCpm - $lastCpm) / ($lastCpm == 0 ? 1 : $lastCpm)),
                    'cpc' => $currentCpc,
                    'cpcDiff' => $currentCpc - $lastCpc,
                    'cpcChange' => min(self::MAX_CHANGE, ($currentCpc - $lastCpc) / ($lastCpc == 0 ? 1 : $lastCpc)),
                ];
            },
            $advertisers
        );

        $result = [
            'data' => $data,
        ];

        return self::json($result);
    }

    public function publishers(Request $request): JsonResponse
    {
        if (strtolower((string)$request->get('g')) === 'user') {
            $emailColumn = 'u.email';
            $domainColumn = 'GROUP_CONCAT(s.domain SEPARATOR ", ")';
            $groupBy = 'u.email';
        } else {
            $emailColumn = 'GROUP_CONCAT(DISTINCT u.email SEPARATOR ", ")';
            $domainColumn = 's.domain';
            $groupBy = 's.domain';
        }

        $viewsLimit = (int)$request->get('l', self::MIN_DAY_VIEWS);
        if (strtolower((string)$request->get('i')) === 'hour') {
            $interval = 1;
            $unit = 'HOUR';
            $viewsLimit /= 24;
        } elseif (strtolower((string)$request->get('i')) === 'day') {
            $interval = 1;
            $unit = 'DAY';
        } else {
            $interval = 7;
            $unit = 'DAY';
            $viewsLimit *= 7;
        }

        $query = '%' . $request->get('q', '') . '%';

        $publishers =
            DB::select(
                $q = sprintf(
                    'SELECT 
                    GROUP_CONCAT(DISTINCT u.id SEPARATOR ",") AS user_ids,
                    %s AS email,
                    %s AS domain,
                    GROUP_CONCAT(s.url SEPARATOR ",") AS url,
                    GROUP_CONCAT(s.rank SEPARATOR ",") AS rank,
                    GROUP_CONCAT(s.info SEPARATOR ",") AS info,
                    SUM(IFNULL(lc.views, 0)) AS current_views,
                    SUM(IFNULL(lc.views_all, 0)) AS current_views_all,
                    SUM(IFNULL(lc.clicks, 0)) AS current_clicks,
                    SUM(IFNULL(lc.revenue, 0)) AS current_revenue,
                    SUM(IFNULL(lp.views, 0)) AS last_views,
                    SUM(IFNULL(lp.views_all, 0)) AS last_views_all,
                    SUM(IFNULL(lp.clicks, 0)) AS last_clicks,
                    SUM(IFNULL(lp.revenue, 0)) AS last_revenue
                FROM sites s
                LEFT JOIN users u ON u.id = s.user_id
                LEFT JOIN (
                    SELECT
                        l.site_id,
                        SUM(l.revenue_hour) AS revenue,
                        SUM(l.views_all) AS views_all,
                        SUM(l.views) AS views,
                        SUM(l.views_unique) AS views_unique,
                        SUM(l.clicks) AS clicks
                    FROM network_case_logs_hourly_stats l
                    WHERE l.hour_timestamp BETWEEN NOW() - INTERVAL %d %s - INTERVAL 2 HOUR
                        AND NOW() - INTERVAL 2 HOUR
                    GROUP BY l.site_id
                ) lc ON lc.site_id = s.uuid
                LEFT JOIN (
                    SELECT
                        l.site_id,
                        SUM(l.revenue_hour) AS revenue,
                        SUM(l.views_all) AS views_all,
                        SUM(l.views) AS views,
                        SUM(l.views_unique) AS views_unique,
                        SUM(l.clicks) AS clicks
                    FROM network_case_logs_hourly_stats l
                    WHERE l.hour_timestamp BETWEEN NOW() - INTERVAL %d %s - INTERVAL 2 HOUR
                        AND NOW() - INTERVAL %d %s - INTERVAL 2 HOUR
                    GROUP BY l.site_id
                ) lp ON lp.site_id = s.uuid
                WHERE s.deleted_at IS NULL AND s.status = %d AND (s.domain LIKE ? OR u.email LIKE ?)
                GROUP BY %s
                HAVING current_views >= ? OR last_views >= ?
                ',
                    $emailColumn,
                    $domainColumn,
                    $interval,
                    $unit,
                    $interval * 2,
                    $unit,
                    $interval,
                    $unit,
                    Site::STATUS_ACTIVE,
                    $groupBy
                ),
                [
                    $query,
                    $query,
                    $viewsLimit,
                    $viewsLimit,
                ]
            );

        $data = array_map(
            function ($row) {
                $currentIvr = ($row->current_views_all - $row->current_views) / max(1, $row->current_views_all);
                $lastIvr = ($row->last_views_all - $row->last_views) / max(1, $row->last_views_all);
                $currentCtr = $row->current_clicks / max(1, $row->current_views);
                $lastCtr = $row->last_clicks / max(1, $row->last_views);
                $currentRpm = (int)($row->current_revenue / max(1, $row->current_views) * 1000);
                $lastRpm = (int)($row->last_revenue / max(1, $row->last_views) * 1000);

                return [
                    'user_ids' => $this->extractUserIds($row->user_ids),
                    'email' => $row->email,
                    'domain' => $row->domain,
                    'url' => $row->url,
                    'rank' => $row->rank,
                    'info' => $row->info,
                    'views' => (int)$row->current_views,
                    'viewsDiff' => $row->current_views - $row->last_views,
                    'viewsChange' => min(
                        self::MAX_CHANGE,
                        ($row->current_views - $row->last_views) / ($row->last_views == 0 ? 1 : $row->last_views)
                    ),
                    'ivr' => $currentIvr,
                    'ivrDiff' => $currentIvr - $lastIvr,
                    'ivrChange' => min(self::MAX_CHANGE, ($currentIvr - $lastIvr) / ($lastIvr == 0 ? 1 : $lastIvr)),
                    'clicks' => (int)$row->current_clicks,
                    'clicksDiff' => $row->current_clicks - $row->last_clicks,
                    'clicksChange' => min(
                        self::MAX_CHANGE,
                        ($row->current_clicks - $row->last_clicks) / ($row->last_clicks == 0 ? 1 : $row->last_clicks)
                    ),
                    'ctr' => $currentCtr,
                    'ctrDiff' => $currentCtr - $lastCtr,
                    'ctrChange' => min(self::MAX_CHANGE, ($currentCtr - $lastCtr) / ($lastCtr == 0 ? 1 : $lastCtr)),
                    'revenue' => (int)$row->current_revenue,
                    'revenueDiff' => $row->current_revenue - $row->last_revenue,
                    'revenueChange' => min(
                        self::MAX_CHANGE,
                        ($row->current_revenue - $row->last_revenue) / ($row->last_revenue == 0 ? 1
                            : $row->last_revenue)
                    ),
                    'rpm' => $currentRpm,
                    'rpmDiff' => $currentRpm - $lastRpm,
                    'rpmChange' => min(self::MAX_CHANGE, ($currentRpm - $lastRpm) / ($lastRpm == 0 ? 1 : $lastRpm)),
                ];
            },
            $publishers
        );

        $result = [
            'data' => $data,
        ];

        return self::json($result);
    }

    private function extractUserIds(string $concatenatedUserIds): array
    {
        return array_map(
            function ($userId) {
                return (int)$userId;
            },
            explode(',', $concatenatedUserIds)
        );
    }
}
