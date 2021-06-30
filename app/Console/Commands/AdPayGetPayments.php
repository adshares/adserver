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

use Adshares\Adserver\Exceptions\Demand\AdPayReportMissingEventsException;
use Adshares\Adserver\Exceptions\Demand\AdPayReportNotReadyException;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\Demand\AdPayPaymentReportProcessor;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTime;
use Illuminate\Support\Collection;

use function count;
use function hex2bin;
use function in_array;
use function now;
use function sprintf;

class AdPayGetPayments extends BaseCommand
{
    public const COMMAND_SIGNATURE = 'ops:adpay:payments:get';

    public const STATUS_OK = 0;

    public const STATUS_LOCKED = 1;

    public const STATUS_CLIENT_EXCEPTION = 2;

    public const STATUS_REQUEST_FAILED = 3;

    protected $signature = self::COMMAND_SIGNATURE . '
                            {--t|timestamp=}
                            {--f|force}
                            {--r|recalculate}
                            {--c|chunkSize=10000}';

    protected $description = 'Updates events with payment data fetched from adpay';

    public function handle(AdPay $adPay, ExchangeRateReader $exchangeRateReader): int
    {
        if (!$this->lock()) {
            $this->info('Command ' . self::COMMAND_SIGNATURE . ' already running');

            return self::STATUS_LOCKED;
        }

        $this->info('Start command ' . self::COMMAND_SIGNATURE);

        $timestamp = $this->getReportTimestamp();
        $exchangeRate = $this->getExchangeRate($exchangeRateReader, new DateTime('@' . $timestamp));
        $limit = (int)$this->option('chunkSize');
        $offset = 0;

        $reportProcessor = new AdPayPaymentReportProcessor($exchangeRate);

        DB::beginTransaction();

        UserLedgerEntry::removeProcessingExpenses();

        do {
            try {
                $calculations = $this->getCalculations($adPay, $timestamp, $limit, $offset);
            } catch (AdPayReportNotReadyException | UnexpectedClientResponseException $recoverableException) {
                $this->info(
                    sprintf(
                        'Exception during fetching payment report from adpay (%s) (%s)',
                        $recoverableException->getCode(),
                        $recoverableException->getMessage()
                    )
                );
                DB::rollBack();
                $this->release();

                return self::STATUS_CLIENT_EXCEPTION;
            } catch (AdPayReportMissingEventsException $unrecoverableException) {
                $this->error(
                    sprintf(
                        'Error during fetching payment report from adpay (%s) (%s)',
                        $unrecoverableException->getCode(),
                        $unrecoverableException->getMessage()
                    )
                );
                DB::rollBack();
                $this->release();

                return self::STATUS_REQUEST_FAILED;
            }
            $calculationsCount = count($calculations);
            $this->info("Found {$calculationsCount} calculations.");

            $eventIds = [];
            $conversionIds = [];
            $mappedCalculations = [];
            $this->mapCalculationsAndSplitIds($calculations, $mappedCalculations, $eventIds, $conversionIds);
            $unpaidEvents = EventLog::fetchUnpaidEventsForUpdateWithPaymentReport($eventIds);
            $unpaidConversions =
                Conversion::fetchUnpaidConversionsForUpdateWithPaymentReport(new Collection($conversionIds));

            $this->info(sprintf('Found %s entries to update.', count($unpaidEvents) + $unpaidConversions->count()));

            foreach ($unpaidEvents as $event) {
                $data = $reportProcessor->processEventLog($event, $mappedCalculations[$event->event_id]);
                DB::table('event_logs')->where('event_id', hex2bin($event->event_id))->update($data);
            }
            foreach ($unpaidConversions as $conversion) {
                $reportProcessor->processConversion($conversion, $mappedCalculations[$conversion->uuid]);
            }

            $offset += $limit;
        } while ($limit === $calculationsCount);

        $ledgerEntriesCount = $this->processExpenses($reportProcessor->getAdvertiserExpenses());
        ConversionDefinition::updateCostAndOccurrences($reportProcessor->getProcessedConversionDefinitions());

        DB::commit();

        $this->info("Created {$ledgerEntriesCount} Ledger Entries.");
        $this->release();

        return self::STATUS_OK;
    }

    private function mapCalculationsAndSplitIds(
        array $calculations,
        array &$mappedCalculations,
        array &$eventIds,
        array &$conversionIds
    ): void {
        foreach ($calculations as $calculation) {
            $mappedCalculations[$calculation['event_id']] = $calculation;

            if (in_array($calculation['event_type'], [EventLog::TYPE_VIEW, EventLog::TYPE_CLICK], true)) {
                $eventIds[] = hex2bin($calculation['event_id']);
            } elseif (Conversion::TYPE === $calculation['event_type']) {
                $conversionIds[] = hex2bin($calculation['event_id']);
            }
        }
    }

    private function getExchangeRate(ExchangeRateReader $exchangeRateReader, DateTime $dateTime): ExchangeRate
    {
        $exchangeRate = $exchangeRateReader->fetchExchangeRate($dateTime);
        $this->info(sprintf('Exchange rate for %s is %f', $dateTime->format('Y-m-d H:i:s'), $exchangeRate->getValue()));

        return $exchangeRate;
    }

    private function getCalculations(AdPay $adPay, int $timestamp, int $limit, int $offset): array
    {
        return $adPay->getPayments(
            $timestamp,
            (bool)$this->option('recalculate'),
            (bool)$this->option('force'),
            $limit,
            $offset
        );
    }

    private function processExpenses(array $expenses): int
    {
        $count = 0;

        foreach ($expenses as $userId => $expense) {
            $entries = UserLedgerEntry::processAdExpense($userId, $expense['total'], $expense['maxBonus']);
            $count += count($entries);
        }

        return $count;
    }

    private function getReportTimestamp(): int
    {
        $timestamp = $this->option('timestamp');

        return $timestamp === null ? now()->subHour(1)->getTimestamp() : (int)$timestamp;
    }
}
