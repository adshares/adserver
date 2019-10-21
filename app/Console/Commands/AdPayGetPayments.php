<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
declare(strict_types = 1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\Demand\AdPayPaymentReportProcessor;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Service\AdPay;
use DateTime;
use Illuminate\Support\Collection;
use function count;
use function hex2bin;
use function in_array;
use function now;
use function sprintf;

class AdPayGetPayments extends BaseCommand
{
    protected $signature = 'ops:adpay:payments:get
                            {--t|timestamp=}
                            {--f|force}
                            {--r|recalculate}
                            {--c|chunkSize=10000}';

    protected $description = 'Updates events with payment data fetched from adpay';

    public function handle(AdPay $adPay, ExchangeRateReader $exchangeRateReader): void
    {
        if (!$this->lock()) {
            $this->info('Command '.$this->signature.' already running');

            return;
        }

        $this->info('Start command '.$this->signature);

        $timestamp = $this->getReportTimestamp();
        $exchangeRate = $this->getExchangeRate($exchangeRateReader, new DateTime('@'.$timestamp));
        $limit = (int)$this->option('chunkSize');
        $offset = 0;

        $reportProcessor = new AdPayPaymentReportProcessor($exchangeRate);
        DB::beginTransaction();

        UserLedgerEntry::removeProcessingExpenses();

        do {
            $calculations = $this->getCalculations($adPay, $timestamp, $limit, $offset);

            $this->info("Found {$calculations->count()} calculations.");

            $eventIds = $this->getEventIds($calculations);
            $unpaidEvents = EventLog::fetchUnpaidEventsForUpdateWithPaymentReport($eventIds);
            $conversionIds = $this->getConversionIds($calculations);
            $unpaidConversions = Conversion::fetchUnpaidConversionsForUpdateWithPaymentReport($conversionIds);

            $this->info(sprintf('Found %s entries to update.', $unpaidEvents->count() + $unpaidConversions->count()));

            $mappedCalculations = $calculations->mapWithKeys(
                static function ($value) {
                    return [$value['event_id'] => $value];
                }
            )->all();

            foreach ($unpaidEvents as $event) {
                $reportProcessor->processEventLog($event, $mappedCalculations[$event->event_id]);
            }
            foreach ($unpaidConversions as $conversion) {
                $reportProcessor->processConversion($conversion, $mappedCalculations[$conversion->uuid]);
            }

            $offset += $limit;
        } while ($limit === $calculations->count());

        $ledgerEntriesCount = $this->processExpenses($reportProcessor->getAdvertiserExpenses());
        ConversionDefinition::updateCostAndOccurrences($reportProcessor->getProcessedConversionDefinitions());

        DB::commit();

        $this->info("Created {$ledgerEntriesCount} Ledger Entries.");
    }

    private function getEventIds(Collection $calculations): Collection
    {
        return $calculations->filter(
            static function (array $calculation) {
                return in_array($calculation['event_type'], [EventLog::TYPE_VIEW, EventLog::TYPE_CLICK], true);
            }
        )->map(
            static function (array $calculation) {
                return hex2bin($calculation['event_id']);
            }
        );
    }

    private function getConversionIds(Collection $calculations): Collection
    {
        return $calculations->filter(
            static function (array $calculation) {
                return Conversion::TYPE === $calculation['event_type'];
            }
        )->map(
            static function (array $calculation) {
                return hex2bin($calculation['event_id']);
            }
        );
    }

    private function getExchangeRate(ExchangeRateReader $exchangeRateReader, DateTime $dateTime): ExchangeRate
    {
        $exchangeRate = $exchangeRateReader->fetchExchangeRate($dateTime);
        $this->info(sprintf('Exchange rate is %f', $exchangeRate->getValue()));

        return $exchangeRate;
    }

    private function getCalculations(AdPay $adPay, int $timestamp, int $limit, int $offset): Collection
    {
        return new Collection(
            $adPay->getPayments(
                $timestamp,
                (bool)$this->option('recalculate'),
                (bool)$this->option('force'),
                $limit,
                $offset
            )
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
        $ts = $this->option('timestamp');

        return $ts === null ? now()->subHour(1)->getTimestamp() : (int)$ts;
    }
}
