<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Adshares\Adserver\Events\ServerEvent;
use Adshares\Adserver\Exceptions\ConsoleCommandException;
use Adshares\Adserver\Mail\TechnicalError;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\PaymentReport;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Exception\InvalidArgumentException;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
            $this->info('Command ' . $this->getName() . ' already running');
            return;
        }

        $this->info('Start command ' . $this->getName());

        PaymentReport::fillMissingReports();
        $lastAvailableTimestamp = self::getLastAvailableTimestamp();
        $isRecalculationNeeded = null !== $this->option('ids');
        $reports = $this->fetchPaymentReports();
        $technicalErrors = [];

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
                } catch (ExchangeRateNotAvailableException $exchangeRateNotAvailableException) {
                    $this->error(
                        sprintf(
                            'Command %s failed %s',
                            AdPayGetPayments::COMMAND_SIGNATURE,
                            $exchangeRateNotAvailableException->getMessage(),
                        )
                    );
                    $technicalErrors[] = sprintf(
                        'Exchange rate not available. Fetching payments for %s failed: %s',
                        (new DateTimeImmutable('@' . $timestamp))->format(DateTimeInterface::ATOM),
                        $exchangeRateNotAvailableException->getMessage(),
                    );
                    continue;
                } catch (LogicException) {
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
                    '--from' => (new DateTimeImmutable('@' . $timestamp))->format(DateTimeInterface::ATOM),
                    '--to' => (new DateTimeImmutable('@' . ($timestamp + 3599)))->format(DateTimeInterface::ATOM),
                ];

                try {
                    Artisan::call(
                        DemandPreparePayments::COMMAND_SIGNATURE,
                        $preparePaymentsParameters,
                        $this->getOutput()
                    );
                } catch (LogicException) {
                    $this->warn(sprintf('Command %s is locked', DemandPreparePayments::COMMAND_SIGNATURE));

                    continue;
                }
                $report->setPrepared();
            }
        }

        $this->sendPayments($reports);
        $this->aggregateStatistics();

        if (!empty($technicalErrors)) {
            $index = strpos($technicalErrors[0], '.');
            $title = false === $index ? $technicalErrors[0] : substr($technicalErrors[0], 0, $index);
            Mail::to(config('app.technical_email'))->queue(
                new TechnicalError($title, join("\n\n", $technicalErrors))
            );
        }
        $this->info('End command ' . $this->getName());
    }

    /**
     * @return Collection<PaymentReport>
     * @throws ConsoleCommandException
     */
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

    private function getReportDateFrom(): DateTimeImmutable
    {
        $optionFrom = $this->option('from');

        if (null === $optionFrom) {
            return new DateTimeImmutable('-2 days');
        }

        if (false === ($timestampFrom = strtotime($optionFrom))) {
            throw new InvalidArgumentException(
                sprintf('[DemandProcessPayments] Invalid option --from format "%s"', $optionFrom)
            );
        }

        return new DateTimeImmutable('@' . $timestampFrom);
    }

    private static function getLastAvailableTimestamp(): int
    {
        return self::getLastExportedEventTimestamp() - PaymentReport::MIN_INTERVAL;
    }

    private static function getLastExportedEventTimestamp(): int
    {
        return min(
            Config::fetchDateTime(Config::ADPAY_LAST_EXPORTED_EVENT_TIME)->getTimestamp(),
            Config::fetchDateTime(Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME)->getTimestamp()
        );
    }

    private function sendPayments(Collection $reports): void
    {
        $preparedReports = $reports->filter(
            function ($report) {
                /** @var PaymentReport $report */
                return $report->isPrepared();
            }
        );

        if ($preparedReports->count() === 0) {
            return;
        }

        DB::beginTransaction();

        /** @var PaymentReport $report */
        foreach ($preparedReports as $report) {
            $report->setDone();
        }

        try {
            $sendStatus = Artisan::call(DemandSendPayments::COMMAND_SIGNATURE, [], $this->getOutput());

            if (DemandSendPayments::STATUS_OK === $sendStatus) {
                DB::commit();
                ServerEvent::dispatch(ServerEventType::OutgoingAdPaymentProcessed);
            } else {
                DB::rollBack();
            }
        } catch (LogicException) {
            DB::rollBack();
            $this->warn(sprintf('Command %s is locked', DemandSendPayments::COMMAND_SIGNATURE));
        }
    }

    private function aggregateStatistics(): void
    {
        try {
            Artisan::call(
                AggregateStatisticsAdvertiserCommand::COMMAND_SIGNATURE,
                [],
                $this->getOutput()
            );
        } catch (LogicException) {
            $this->warn(
                sprintf('Command %s is locked', AggregateStatisticsAdvertiserCommand::COMMAND_SIGNATURE)
            );
        }
    }
}
