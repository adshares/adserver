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
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Demand\Application\Service\AdPay;
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

    public function handle(AdPay $adPay): void
    {
        $this->info('Start command '.$this->signature);
        DB::beginTransaction();

        UserLedgerEntry::removeBlockade();

        $ts = $this->option('timestamp');
        $timestamp = $ts === null ? now()->subHour((int)$this->option('sub'))->getTimestamp() : (int)$ts;

        $calculations = collect($adPay->getPayments($timestamp, (bool)$this->option('force')));

        Log::info('Found '.count($calculations).' calculations.');

        $eventIds = $calculations->map(function (array $amount) {
            return hex2bin($amount['event_id']);
        });

        $unpaidEvents = EventLog::whereIn('event_id', $eventIds)
            ->whereNull('event_value')
            ->get();

        Log::info('Found '.count($unpaidEvents).' entries to update.');


        $ledgerUnpaidEvents = $unpaidEvents->groupBy(function (EventLog $entry) {
            return $entry->advertiser()->id;
        })->map(function (Collection $collection, int $userId) use ($calculations) {
            $collection->each(function (EventLog $entry) use ($calculations) {
                $calculation = $calculations->firstWhere('event_id', $entry->event_id);
                $entry->event_value = $calculation['amount'];
                $entry->reason = $calculation['reason'];
                $entry->save();
            });

            $balance = UserLedgerEntry::getBalanceByUserId($userId);
            $totalEventValue = $collection->sum('event_value');

            if ($balance < $totalEventValue) {
                $collection->each(function (EventLog $entry) use ($balance, $totalEventValue) {
                    $entry->event_value = floor($entry->event_value * $balance / $totalEventValue);
                    $entry->save();
                });

                Campaign::fetchByUserId($userId)->filter(function (Campaign $campaign) {
                    return $campaign->status === Campaign::STATUS_ACTIVE;
                })->each(function (Campaign $campaign) {
                    $campaign->changeStatus(Campaign::STATUS_SUSPENDED);
                    $campaign->save();
                });

                Log::debug("Suspended Campaigns for user [$userId] due to insufficient amount of clicks."
                    ." Needs $totalEventValue, but has $balance");

                $totalEventValue = $collection->sum('event_value');
            }

            if ($totalEventValue > 0) {
                $userLedgerEntry = UserLedgerEntry::construct(
                    $userId,
                    -$totalEventValue,
                    UserLedgerEntry::STATUS_ACCEPTED,
                    UserLedgerEntry::TYPE_AD_EXPENDITURE
                );

                $userLedgerEntry->save();

                return $userLedgerEntry;
            }

            return false;
        })->filter();

        DB::commit();

        Log::info('Created '.count($ledgerUnpaidEvents).' Ledger Entries.');
    }
}
