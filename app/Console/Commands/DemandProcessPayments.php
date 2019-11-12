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

use Adshares\Adserver\Exceptions\ConsoleCommandException;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\PaymentReport;
use Adshares\Common\Exception\InvalidArgumentException;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\LogicException;

class DemandProcessPayments extends BaseCommand
{
    protected $signature = 'ops:demand:payments:process
                            {--f|from= : Date from which reports will be processed}
                            {--ids= : Report ids to process}';

    protected $description = 'Fetches reports, sends payments and updates statistics';

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command '.$this->getName().' already running');

            return;
        }

        $this->info('Start command '.$this->getName());

        PaymentReport::fillMissingReports();
        $lastAvailableTimestamp = $this->getLastAvailableTimestamp();
        $isRecalculationNeeded = null !== $this->option('ids');
        $reports = $this->fetchPaymentReports();

        /** @var PaymentReport $report */
        foreach ($reports as $report) {
            $timestamp = $report->id;
            if ($timestamp > $lastAvailableTimestamp) {
                continue;
            }

            if ($report->isNew()) {
                $getPaymentsParameters = ['--timestamp' => $timestamp];
                if ($isRecalculationNeeded) {
                    $getPaymentsParameters['--recalculate'] = 1;
                }

                try {
                    $status =
                        Artisan::call(
                            AdPayGetPayments::COMMAND_SIGNATURE,
                            $getPaymentsParameters,
                            $this->getOutput()
                        );
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
                $preparePaymentsParameters = [
                    '--from' => (new DateTime('@'.$timestamp))->format(DateTime::ATOM),
                    '--to' => (new DateTime('@'.($timestamp + 3599)))->format(DateTime::ATOM),
                ];

                try {
                    Artisan::call(
                        DemandPreparePayments::COMMAND_SIGNATURE,
                        $preparePaymentsParameters,
                        $this->getOutput()
                    );
                } catch (LogicException $logicException) {
                    $this->warn(sprintf('Command %s is locked', DemandPreparePayments::COMMAND_SIGNATURE));

                    continue;
                }
                $report->setPrepared();
            }

            if ($report->isPrepared()) {
                try {
                    Artisan::call(DemandSendPayments::COMMAND_SIGNATURE, [], $this->getOutput());
                } catch (LogicException $logicException) {
                    $this->warn(sprintf('Command %s is locked', DemandSendPayments::COMMAND_SIGNATURE));

                    continue;
                }
                $report->setSent();
            }

            if ($report->isSent()) {
                $hour = (new DateTime('@'.$timestamp))->format(DateTime::ATOM);
                try {
                    Artisan::call(
                        AggregateStatisticsAdvertiserCommand::COMMAND_SIGNATURE,
                        ['--hour' => $hour],
                        $this->getOutput()
                    );
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

    private function fetchPaymentReports(): Collection
    {
        if (null !== ($ids = $this->option('ids'))) {
            $reports = PaymentReport::fetchByIds(explode(',', $ids));

            if ($reports->isEmpty()) {
                throw new ConsoleCommandException(sprintf('Payment reports "%s" not found', $ids));
            }

            return $reports->each(
                function (PaymentReport $report) {
                    $report->setNew();
                }
            );
        }

        return PaymentReport::fetchUndone($this->getReportDateFrom());
    }

    private function getReportDateFrom(): DateTime
    {
        $optionFrom = $this->option('from');

        if (null === $optionFrom) {
            return new DateTime('-2 days');
        }

        if (false === ($timestampFrom = strtotime($optionFrom))) {
            throw new InvalidArgumentException(
                sprintf('[DemandProcessPayments] Invalid option --from format "%s"', $optionFrom)
            );
        }

        return new DateTime('@'.$timestampFrom);
    }

    private function getLastAvailableTimestamp(): int
    {
        return $this->getLastExportedEventTimestamp() - PaymentReport::MIN_INTERVAL;
    }

    private function getLastExportedEventTimestamp(): int
    {
        return min(
            Config::fetchDateTime(Config::ADPAY_LAST_EXPORTED_EVENT_TIME)->getTimestamp(),
            Config::fetchDateTime(Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME)->getTimestamp()
        );
    }
}
