<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    const MIN_DAY_VIEWS = 1000;

    const MAX_CHANGE = 9.999;

    public function browse(Request $request): LengthAwarePaginator
    {
        $query = $request->get('q');
        if ($query) {
            $domains =
                DB::select(
                    'SELECT DISTINCT user_id from sites WHERE deleted_at IS NULL AND domain LIKE ? LIMIT 100',
                    ['%'.$query.'%']
                );
            $campaigns =
                DB::select(
                    'SELECT DISTINCT user_id from campaigns WHERE deleted_at IS NULL AND landing_url LIKE ? LIMIT 100',
                    ['%'.$query.'%']
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

            return User::where('email', 'LIKE', '%'.$query.'%')->orWhereIn('id', $ids)->paginate();
        }

        return User::paginate();
    }

    public function publishers(Request $request): JsonResponse
    {
        if (strtolower((string)$request->get('g')) === 'user') {
            $emailColumn = 'u.email';
            $domainColumn = 'GROUP_CONCAT(DISTINCT s.domain SEPARATOR ", ")';
            $groupBy = 'u.email';
        } else {
            $emailColumn = 'GROUP_CONCAT(DISTINCT u.email SEPARATOR ", ")';
            $domainColumn = 's.domain';
            $groupBy = 's.domain';
        }

        if (strtolower((string)$request->get('i')) === 'day') {
            $interval = 1;
        } else {
            $interval = 7;
        }

        $viewsLimit = (int)$request->get('l', self::MIN_DAY_VIEWS);
        $query = '%'.$request->get('q', '').'%';

        $publishers =
            DB::select(
                $q = sprintf(
                    'SELECT 
                    %s AS email,
                    %s AS domain,
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
                    WHERE l.hour_timestamp BETWEEN NOW() - INTERVAL %d DAY - INTERVAL 2 HOUR
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
                    WHERE l.hour_timestamp BETWEEN NOW() - INTERVAL %d DAY - INTERVAL 2 HOUR
                        AND NOW() - INTERVAL %d DAY - INTERVAL 2 HOUR
                    GROUP BY l.site_id
                ) lp ON lp.site_id = s.uuid
                WHERE s.deleted_at IS NULL AND s.status = %d AND (s.domain LIKE ? OR u.email LIKE ?)
                GROUP BY %s
                HAVING current_views >= ? OR last_views >= ?
                ',
                    $emailColumn,
                    $domainColumn,
                    $interval,
                    $interval * 2,
                    $interval,
                    Site::STATUS_ACTIVE,
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
                $currentIvr = ($row->current_views_all - $row->current_views) / max(1, $row->current_views_all);
                $lastIvr = ($row->last_views_all - $row->last_views) / max(1, $row->last_views_all);
                $currentCtr = $row->current_clicks / max(1, $row->current_views);
                $lastCtr = $row->last_clicks / max(1, $row->last_views);
                $currentRpm = (int)($row->current_revenue / max(1, $row->current_views) * 1000);
                $lastRpm = (int)($row->last_revenue / max(1, $row->last_views) * 1000);

                return [
                    'email' => $row->email,
                    'domain' => $row->domain,
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
}
