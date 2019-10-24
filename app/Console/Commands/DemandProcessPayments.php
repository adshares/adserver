<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\PaymentReport;
use DateTime;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\LogicException;

class DemandProcessPayments extends BaseCommand
{
    protected $signature = 'ops:demand:payments:process
                            {--p|period= : Maximal period (seconds) that will be searched for reports}';

    protected $description = 'Fetches reports, sends payments and updates statistics';

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command '.$this->getName().' already running');

            return;
        }

        $this->info('Start command '.$this->getName());

        PaymentReport::fillMissingReports();
        $optionPeriod = $this->option('period');
        $reports = $optionPeriod ? PaymentReport::fetchUndone((int)$optionPeriod) : PaymentReport::fetchUndone();

        /** @var PaymentReport $report */
        foreach ($reports as $report) {
            $timestamp = $report->id;

            if ($report->isNew()) {
                try {
                    $status = Artisan::call(AdPayGetPayments::COMMAND_SIGNATURE, ['--timestamp' => $timestamp]);
                } catch (LogicException $logicException) {
                    $this->warn(sprintf('Command %s is locked', AdPayGetPayments::COMMAND_SIGNATURE));

                    continue;
                }

                if (AdPayGetPayments::STATUS_REQUEST_FAILED === $status) {
                    $report->setFailed();
                    continue;
                } elseif (AdPayGetPayments::STATUS_OK === $status) {
                    $report->setUpdated();
                } else {
                    continue;
                }
            }

            if ($report->isUpdated()) {
                $parameters = $optionPeriod ? ['--period' => $optionPeriod] : [];
                try {
                    Artisan::call(DemandPreparePayments::COMMAND_SIGNATURE, $parameters);
                } catch (LogicException $logicException) {
                    $this->warn(sprintf('Command %s is locked', DemandPreparePayments::COMMAND_SIGNATURE));

                    continue;
                }
                $report->setPrepared();
            }

            if ($report->isPrepared()) {
                try {
                    Artisan::call(DemandSendPayments::COMMAND_SIGNATURE);
                } catch (LogicException $logicException) {
                    $this->warn(sprintf('Command %s is locked', DemandSendPayments::COMMAND_SIGNATURE));

                    continue;
                }
                $report->setSent();
            }

            if ($report->isSent()) {
                $hour = (new DateTime('@'.$timestamp))->format(DateTime::ATOM);
                try {
                    Artisan::call(AggregateStatisticsAdvertiserCommand::COMMAND_SIGNATURE, ['--hour' => $hour]);
                } catch (LogicException $logicException) {
                    $this->warn(
                        sprintf('Command %s is locked', AggregateStatisticsAdvertiserCommand::COMMAND_SIGNATURE)
                    );

                    continue;
                }
                $report->setDone();
            }
        }

        $this->info('End command '.$this->getName());
    }
}
