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

use Adshares\Adserver\Facades\DB;

use function sprintf;

class ClearEvents extends BaseCommand
{
    protected $signature = 'ops:events:clear {--b|before=} {--p|period=P32D} {--c|chunkSize=1000}';

    protected $description = 'Clear event and network event logs';

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info(sprintf('Command %s already running', $this->signature));

            return;
        }
        $this->info(sprintf('Start command %s', $this->signature));

        $chunkSize = (int)$this->option('chunkSize');
        $period = $this->option('period');
        $before = $this->option('before');

        try {
            if ($before !== null) {
                $dateTo = new \DateTime($before);
            } else {
                $dateTo = (new \DateTime())->sub(new \DateInterval($period));
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->info(sprintf(
            'Clearing events older than %s with chunk size %d',
            $dateTo->format('c'),
            $chunkSize
        ));

        $this->clearTable('event_logs', $dateTo, $chunkSize);
        $this->clearTable('network_impressions', $dateTo, $chunkSize);
        $this->clearTable('network_cases', $dateTo, $chunkSize);
        $this->clearTable('network_case_clicks', $dateTo, $chunkSize);
        $this->clearTable('network_case_payments', $dateTo, $chunkSize, 'pay_time');

        $this->info('Finish clearing events');
    }

    private function clearTable(
        string $table,
        \DateTime $dateTo,
        int $chunkSize,
        string $time_column = 'created_at'
    ): int {
        $this->getOutput()->write(sprintf('<info>Clearing %s', $table));

        $deleted = 0;
        $firstLeftRecord = DB::selectOne(
            sprintf(
                'SELECT id AS value FROM %s WHERE %s >= ? ORDER BY %s ASC, id ASC LIMIT 1',
                $table,
                $time_column,
                $time_column
            ),
            [$dateTo]
        );

        if (null === $firstLeftRecord) {
            $last = (int)DB::selectOne(sprintf('SELECT MAX(id) AS value FROM %s', $table))->value;

            if (!$last) {
                $this->getOutput()->writeln('</info>');
                $this->info(sprintf('Table %s is empty.', $table));

                return 0;
            }
        } else {
            $last = (int)$firstLeftRecord->value - 1;
        }

        do {
            DB::beginTransaction();
            $this->getOutput()->write('.');
            $offset = (int)DB::selectOne(
                sprintf('SELECT MIN(id) AS value FROM %s', $table)
            )->value;
            $count = DB::delete(
                sprintf('DELETE FROM %s WHERE id BETWEEN ? AND ?', $table),
                [
                    $offset,
                    min($last, $offset + $chunkSize - 1),
                ]
            );
            DB::commit();
            $deleted += $count;
        } while ($count > 0);

        $this->getOutput()->writeln('</info>');
        $this->info(sprintf('Deleted %d rows from %s', $deleted, $table));

        return $deleted;
    }
}
