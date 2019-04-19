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

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Service\AdPay;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use function collect;
use function floor;
use function now;

class AdPayGetPayments extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:adpay:payments:get {--t|timestamp=} {--s|sub=1} {--f|force}';

    public function handle(AdPay $adPay, ExchangeRateReader $exchangeRateReader): void
    {
        $this->info('Start command '.$this->signature);

        DB::beginTransaction();

        UserLedgerEntry::removeProcessingExpenses();

        $ts = $this->option('timestamp');
        $timestamp = $ts === null ? now()->subHour((int)$this->option('sub'))->getTimestamp() : (int)$ts;

        $calculations = collect($adPay->getPayments($timestamp, (bool)$this->option('force')));

        $this->info('Found '.count($calculations).' calculations.');

        $exchangeRate = $exchangeRateReader->fetchExchangeRate(new DateTime());
        $this->info(sprintf('Current exchange rate is %f', $exchangeRate->getValue()));

        $eventIds = $calculations->map(function (array $amount) {
            return hex2bin($amount['event_id']);
        });

        $unpaidEvents = EventLog::whereIn('event_id', $eventIds)
            ->whereNull('event_value_currency')
            ->get();

        $this->info('Found '.count($unpaidEvents).' entries to update.');

        $unpaidEvents->each(function (EventLog $entry) use ($calculations, $exchangeRate) {
            $calculation = $calculations->firstWhere('event_id', $entry->event_id);
            $amount = $calculation['amount'];

            $entry->event_value_currency = $amount;
            $entry->exchange_rate = $exchangeRate->getValue();
            $entry->event_value = $exchangeRate->toClick($amount);
            $entry->reason = $calculation['reason'];
        });

        $unpaidEvents->groupBy(function (EventLog $entry) {
            return $entry->campaign_id;
        })->each(function (Collection $singleCampaignEvents, string $campaignPublicId) use ($exchangeRate) {
            $campaign = Campaign::fetchByUuid($campaignPublicId);

            if (!$campaign) {
                Log::warning(
                    sprintf(
                        '{"error":"no-campaign","command":"ops:adpay:payments:get","uuid":"%s"}',
                        $campaignPublicId
                    )
                );
                return true;
            }

            $maxSpendableAmount = (int)$campaign->budget;
            $totalEventValue = $singleCampaignEvents->sum('event_value_currency');

            if ($maxSpendableAmount < $totalEventValue) {
                $normalizationFactor = (float)$maxSpendableAmount / $totalEventValue;
                $singleCampaignEvents->each(function (EventLog $entry) use ($normalizationFactor, $exchangeRate) {
                    $amount = (int)floor($entry->event_value_currency * $normalizationFactor);
                    $entry->event_value_currency = $amount;
                    $entry->event_value = $exchangeRate->toClick($amount);
                });
            }
        })->flatten(1);

        $unpaidLedgerEntries = $unpaidEvents->groupBy(function (EventLog $entry) {
            return $entry->advertiser_id;
        })->map(function (Collection $singleUserEvents, string $userPublicId) use ($exchangeRate) {
            $user = User::fetchByUuid($userPublicId);

            if (!$user) {
                throw new Exception(
                    sprintf(
                        '{"error":"no-user","command":"ops:adpay:payments:get","advertiser_id":"%s"}',
                        $userPublicId
                    )
                );
            }

            $maxSpendableAmount = $exchangeRate->fromClick($user->getBalance());
            $totalEventValue = $singleUserEvents->sum('event_value_currency');

            if ($maxSpendableAmount < $totalEventValue) {
                $normalizationFactor = (float)$maxSpendableAmount / $totalEventValue;
                $singleUserEvents->each(function (EventLog $entry) use ($normalizationFactor, $exchangeRate) {
                    $amount = (int)floor($entry->event_value_currency * $normalizationFactor);
                    $entry->event_value_currency = $amount;
                    $entry->event_value = $exchangeRate->toClick($amount);
                });

                Campaign::suspendAllForUserId($user->id);

                Log::debug("Suspended Campaigns for user [{$user->id}] due to insufficient amount of clicks."
                    ." Needs $totalEventValue, but has $maxSpendableAmount");

                $totalEventValue = $singleUserEvents->sum('event_value_currency');
            }

            $singleUserEvents->each(function (EventLog $entry) {
                $entry->save();
            });

            if ($totalEventValue > 0) {
                return UserLedgerEntry::processAdExpense($user->id, $totalEventValue);
            }

            return false;
        })->filter();

        DB::commit();

        $this->info('Created '.count($unpaidLedgerEntries).' Ledger Entries.');
    }
}
