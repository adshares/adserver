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
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Supply\Application\Service\AdSelectEventExporter;
use function sprintf;

class AdSelectPaymentsExportCommand extends BaseCommand
{
    protected $signature = 'ops:adselect:payment:export';

    protected $description = 'Export event payments to AdSelect';

    protected $exporterService;

    public function __construct(Locker $locker, AdSelectEventExporter $exporterService)
    {
        parent::__construct($locker);

        $this->exporterService = $exporterService;
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('[AdSelectPaymentsExport] Command '.$this->signature.' already running');

            return;
        }

        $this->info('Start command '.$this->signature);

        $lastExportedAdsPaymentId = Config::fetchInt(Config::ADSELECT_LAST_EXPORTED_ADS_PAYMENT_ID);

        $greatestAdsPaymentIdInDatabase =
            NetworkEventLog::where('ads_payment_id', '>', $lastExportedAdsPaymentId)->max('ads_payment_id');

        if (null === $greatestAdsPaymentIdInDatabase) {
            $this->info('[ADSELECT] No paid events to export');

            return;
        }

        $adsPaymentIdStart = $lastExportedAdsPaymentId + 1;
        $adsPaymentIdEnd = $greatestAdsPaymentIdInDatabase;

        $this->info(
            sprintf(
                '[ADSELECT] Trying to export paid events with ads_payment_id <%d;%d>',
                $adsPaymentIdStart,
                $adsPaymentIdEnd
            )
        );

        $exported = $this->exporterService->exportPaidEvents($adsPaymentIdStart, $adsPaymentIdEnd);
        $this->info(
            sprintf(
                '[ADSELECT] Exported %s paid events',
                $exported
            )
        );

        Config::upsertInt(Config::ADSELECT_LAST_EXPORTED_ADS_PAYMENT_ID, $adsPaymentIdEnd);

        $this->info('Finished exporting event payments to AdSelect');
    }
}
