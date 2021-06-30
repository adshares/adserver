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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SiteRankReassessRequestCommand extends BaseCommand
{
    protected $signature = 'ops:supply:site-rank:reassess';

    protected $description = "Requests reassessment of sites' rank";

    /** @var AdUser */
    private $adUser;

    private const SELECT_BY_VIEWS = <<<SQL
SELECT sites.id AS id, sites.url AS url, stats.views AS views
FROM (SELECT site_id, SUM(views) AS views
      FROM network_case_logs_hourly_stats
      WHERE zone_id IS NULL
        AND hour_timestamp > NOW() - INTERVAL 7 DAY
      GROUP BY 1) AS stats
         JOIN sites ON stats.site_id = sites.uuid
WHERE stats.views > 1000
  AND sites.reassess_available_at < NOW()
  AND sites.info <> 'unknown';
SQL;

    private const SELECT_BY_CTR = <<<SQL
SELECT sites.id AS id, sites.url AS url, stats.views AS views, stats.ctr AS ctr
FROM (SELECT site_id, SUM(views) AS views, IFNULL(SUM(clicks) / SUM(views), 0) AS ctr
      FROM network_case_logs_hourly_stats
      WHERE zone_id IS NULL
         AND hour_timestamp > NOW() - INTERVAL 7 DAY
      GROUP BY 1) AS stats
         JOIN sites ON stats.site_id = sites.uuid
WHERE stats.views > 500
  AND stats.ctr > 0.05
  AND sites.reassess_available_at < NOW()
  AND sites.info <> 'unknown';
SQL;

    private const SELECT_BY_REVENUE = <<<SQL
SELECT sites.id AS id, sites.url AS url, stats.revenue AS revenue
FROM (SELECT site_id, SUM(revenue_case) AS revenue
      FROM network_case_logs_hourly_stats
      WHERE zone_id IS NULL
        AND hour_timestamp > NOW() - INTERVAL 7 DAY
      GROUP BY 1
      ORDER BY revenue DESC
      LIMIT 30) AS stats
         JOIN sites ON stats.site_id = sites.uuid
WHERE stats.revenue > 0
  AND sites.reassess_available_at < NOW()
  AND sites.info <> 'unknown';
SQL;

    public function __construct(Locker $locker, AdUser $adUser)
    {
        parent::__construct($locker);

        $this->adUser = $adUser;
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->name . ' already running');

            return;
        }

        $this->info('Start command ' . $this->name);

        $sites = $this->selectSites();
        $this->update($sites);

        $this->info('Finish command ' . $this->name);
    }

    private function selectSites(): array
    {
        $sites = [];

        foreach (DB::select(self::SELECT_BY_VIEWS) as $row) {
            $sites[$row->id]['url'] = $row->url;
            $sites[$row->id]['extra'][] = [
                'reason' => 'many views',
                'message' => 'Views: ' . NumberFormat::toFormattedString($row->views, '#,##0'),
            ];
        }

        foreach (DB::select(self::SELECT_BY_CTR) as $row) {
            $sites[$row->id]['url'] = $row->url;
            $sites[$row->id]['extra'][] = [
                'reason' => 'high ctr',
                'message' => sprintf(
                    'CTR: %s (for %s views)',
                    NumberFormat::toFormattedString($row->ctr, NumberFormat::FORMAT_PERCENTAGE_00),
                    NumberFormat::toFormattedString($row->views, '#,##0')
                ),
            ];
        }

        foreach (DB::select(self::SELECT_BY_REVENUE) as $row) {
            $sites[$row->id]['url'] = $row->url;
            $sites[$row->id]['extra'][] = [
                'reason' => 'top revenue',
                'message' => trim(
                    'TOP 30 revenue: ' . NumberFormat::toFormattedString(
                        $row->revenue / 1e11,
                        NumberFormat::FORMAT_CURRENCY_USD_SIMPLE
                    )
                ),
            ];
        }

        return $sites;
    }

    private function update(array $sites): void
    {
        if (empty($sites)) {
            return;
        }
        $ids = [];
        $urls = [];
        $index = 0;
        foreach ($sites as $id => $data) {
            $ids[$index] = $id;
            $urls[$index] = $data;
            ++$index;
        }

        try {
            $results = $this->adUser->reassessPageRankBatch($urls);
        } catch (UnexpectedClientResponseException $unexpectedClientResponseException) {
            $this->error($unexpectedClientResponseException->getMessage());

            return;
        } catch (RuntimeException $exception) {
            $this->warn($exception->getMessage());

            return;
        }

        DB::beginTransaction();
        try {
            foreach ($results as $index => $result) {
                if (!array_key_exists($index, $ids)) {
                    $this->warn(sprintf('Invalid index (%s) in response', $index));

                    continue;
                }

                if (!isset($result['status'])) {
                    $this->warn(
                        sprintf(
                            'Missing `status` for an URL (%s) from site id (%d)',
                            $urls[$index]['url'],
                            $ids[$index]
                        )
                    );

                    continue;
                }

                $status = $result['status'];

                if (AdUser::REASSESSMENT_STATE_INVALID_URL === $status) {
                    $this->warn(
                        sprintf(
                            'Invalid URL error for an URL (%s) from site id (%d)',
                            $urls[$index]['url'],
                            $ids[$index]
                        )
                    );

                    continue;
                }

                if (AdUser::REASSESSMENT_STATE_ERROR === $status) {
                    $this->warn(
                        sprintf(
                            'Reassessment did not complete for an URL (%s) from site id (%d)',
                            $urls[$index]['url'],
                            $ids[$index]
                        )
                    );

                    continue;
                }

                if (AdUser::REASSESSMENT_STATE_NOT_REGISTERED === $status) {
                    $this->warn(
                        sprintf('Not registered URL (%s) from site id (%d)', $urls[$index]['url'], $ids[$index])
                    );

                    continue;
                }

                if (
                    in_array(
                        $status,
                        [
                        AdUser::REASSESSMENT_STATE_PROCESSING,
                        AdUser::REASSESSMENT_STATE_LOCKED,
                        AdUser::REASSESSMENT_STATE_ACCEPTED,
                        ]
                    )
                ) {
                    if (isset($result['reassess_available_at'])) {
                        $date = DateTimeImmutable::createFromFormat(
                            DateTimeImmutable::ATOM,
                            $result['reassess_available_at']
                        );
                        if (!$date) {
                            $this->warn(
                                sprintf(
                                    'Invalid field `reassess_available_at` format (%s) URL (%s) from site id (%d)',
                                    $result['reassess_available_at'],
                                    $urls[$index]['url'],
                                    $ids[$index]
                                )
                            );

                            $date = self::getNextReassessmentDate();
                        }
                    } else {
                        $date = self::getNextReassessmentDate();
                    }

                    DB::update('UPDATE sites SET reassess_available_at=? WHERE id=?', [$date, $ids[$index]]);

                    continue;
                }

                $this->warn(
                    sprintf(
                        'Unknown `status` (%s) for an URL (%s) from site id (%d)',
                        $status,
                        $urls[$index]['url'],
                        $ids[$index]
                    )
                );
            }
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();

            $this->info('Sites were not updated due an exception (%s)', $exception->getMessage());
        }
    }

    private static function getNextReassessmentDate(): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('+%d days', rand(7, 30)));
    }
}
