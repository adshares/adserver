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

use Adshares\Adserver\Models\ReportMeta;
use Adshares\Adserver\Services\Common\ReportsStorage;
use DateInterval;
use DateTime;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

use function sprintf;

class ClearReports extends BaseCommand
{
    protected $signature = 'ops:reports:clear {--p|period=P7D}';

    protected $description = 'Clear reports';

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info(sprintf('Command %s already running', $this->name));

            return;
        }
        $this->info(sprintf('Start command %s', $this->name));

        $period = $this->option('period');

        try {
            $dateTo = (new DateTime())->sub(new DateInterval($period));
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->info(sprintf('Clearing reports older than %s', $dateTo->format('c')));

        $reports = ReportMeta::fetchOlderThan($dateTo);
        foreach ($reports as $report) {
            /** @var ReportMeta $report */
            $uuid = $report->uuid;

            DB::beginTransaction();

            try {
                if ($report->delete() && ReportsStorage::delete($uuid)) {
                    DB::commit();
                    continue;
                }

                DB::rollBack();
                $this->warn(sprintf('Cannot delete report (%s)', $uuid));
            } catch (Throwable $throwable) {
                DB::rollBack();
                $this->warn(sprintf('Cannot delete report (%s) due to error (%s)', $uuid, $throwable->getMessage()));
            }
        }

        $this->info('Finish clearing reports');
    }
}
