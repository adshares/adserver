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

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Supply\Application\Service\AdSelectEventExporter;
use DateTime;
use function sprintf;

class AdSelectEventExportCommand extends BaseCommand
{
    protected $signature = 'ops:adselect:event:export';

    protected $description = 'Export events to AdSelect';

    protected $exporterService;

    public function __construct(Locker $locker, AdSelectEventExporter $exporterService)
    {
        $this->exporterService = $exporterService;

        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('[AdSelectEventExport] Command '.$this->signature.' already running.');

            return;
        }

        $this->info('[AdSelectEventExport] Start command '.$this->signature);

        $lastExportDate = Config::fetchDateTime(Config::ADSELECT_EVENT_EXPORT_TIME);

        $this->info(sprintf(
            '[ADSELECT] Trying to export unpaid events from %s',
            $lastExportDate->format(DateTime::ATOM)
        ));

        $exported = $this->exporterService->exportUnpaidEvents($lastExportDate);

        $this->info(sprintf(
            '[ADSELECT] Exported %s unpaid events',
            $exported
        ));

        Config::upsertDateTime(Config::ADSELECT_EVENT_EXPORT_TIME, new DateTime());

        $this->info('[AdSelectEventExport] Finished exporting events to AdSelect.');
    }
}
