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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Models\Config;
use Adshares\Supply\Application\Service\AdSelectEventExporter;
use Adshares\Supply\Application\Service\Exception\NoEventsForGivenTimePeriod;
use DateTime;
use Illuminate\Console\Command;

class AdSelectPaymentsExportCommand extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:adselect:payment:export';

    protected $description = 'Export event payments to AdSelect';

    protected $exporterService;

    public function __construct(AdSelectEventExporter $exporterService)
    {
        parent::__construct();

        $this->exporterService = $exporterService;
    }

    public function handle(): void
    {
        $this->info('Start command '.$this->signature);

        $lastExportDate = Config::fetchDateTime(Config::ADSELECT_PAYMENT_EXPORT_TIME);

        try {
            $this->exporterService->exportPayments($lastExportDate);
        } catch (NoEventsForGivenTimePeriod $exception) {
            $this->info($exception->getMessage());

            return;
        }

        Config::upsertDateTime(Config::ADSELECT_PAYMENT_EXPORT_TIME, new DateTime());

        $this->info('Finished exporting event payments to AdSelect.');
    }
}
