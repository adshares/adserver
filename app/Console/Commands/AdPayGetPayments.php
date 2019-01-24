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

        UserLedgerEntry::removeProcessingExpenditures();

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

        $unpaidEvents->each(function (EventLog $entry) use ($calculations) {
            $calculation = $calculations->firstWhere('event_id', $entry->event_id);
            $entry->event_value = $calculation['amount'];
            $entry->reason = $calculation['reason'];
        });

        $unpaidEvents->groupBy(function (EventLog $entry) {
            return $entry->campaign_id;
        })->each(function (Collection $singleCampaignEvents, string $campaignPublicId) {
            $campaign = Campaign::fetchByUuid($campaignPublicId);

            if (!$campaign) {
                throw new Exception(
                    sprintf(
                        '{"error":"no-campaign","command":"ops:adpay:payments:get","uuid":"%s"}',
                        $campaignPublicId
                    )
                );
            }

            $maxSpendableAmount = $campaign->budget;
            $totalEventValue = $singleCampaignEvents->sum('event_value');

            if ($maxSpendableAmount < $totalEventValue) {
                $singleCampaignEvents->each(function (EventLog $entry) use ($maxSpendableAmount, $totalEventValue) {
                    $entry->event_value = floor($entry->event_value * $maxSpendableAmount / $totalEventValue);
                });
            }
        })->flatten(1);

        $unpaidLedgerEntries = $unpaidEvents->groupBy(function (EventLog $entry) {
            return $entry->advertiser_id;
        })->map(function (Collection $singleUserEvents, string $userPublicId) {
            $user = User::fetchByUuid($userPublicId);

            if (!$user) {
                throw new Exception(
                    sprintf(
                        '{"error":"no-user","command":"ops:adpay:payments:get","advertiser_id":"%s"}',
                        $userPublicId
                    )
                );
            }

            $maxSpendableAmount = $user->getBalance();
            $totalEventValue = $singleUserEvents->sum('event_value');

            if ($maxSpendableAmount < $totalEventValue) {
                $singleUserEvents->each(function (EventLog $entry) use ($maxSpendableAmount, $totalEventValue) {
                    $entry->event_value = floor($entry->event_value * $maxSpendableAmount / $totalEventValue);
                });

                Campaign::suspendAllForUserId($user->id);

                Log::debug("Suspended Campaigns for user [{$user->id}] due to insufficient amount of clicks."
                    ." Needs $totalEventValue, but has $maxSpendableAmount");

                $totalEventValue = $singleUserEvents->sum('event_value');
            }

            $singleUserEvents->each(function (EventLog $entry) {
                $entry->save();
            });

            if ($totalEventValue > 0) {
                $userLedgerEntry = UserLedgerEntry::construct(
                    $user->id,
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

        Log::info('Created '.count($unpaidLedgerEntries).' Ledger Entries.');
    }
}
