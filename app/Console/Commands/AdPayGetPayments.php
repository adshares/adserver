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
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Service\AdPay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use function floor;
use function now;
use function sprintf;

class AdPayGetPayments extends BaseCommand
{
    private const EVENT_VALUE_CURRENCY = 'event_value_currency';

    private const EVENT_VALUE = 'event_value';

    public const DIRECT = 'direct';

    public const NORMAL = 'normal';

    protected $signature = 'ops:adpay:payments:get {--t|timestamp=} {--s|sub=1} {--f|force}';

    public function handle(AdPay $adPay, ExchangeRateReader $exchangeRateReader): void
    {
        if (!$this->lock()) {
            $this->info('Command '.$this->signature.' already running');

            return;
        }

        $this->info('Start command '.$this->signature);

        DB::beginTransaction();

        UserLedgerEntry::removeProcessingExpenses();

        $calculations = $this->getCalculations($adPay);

        $this->info("Found {$calculations->count()} calculations.");

        $exchangeRate = $this->getExchangeRate($exchangeRateReader);
        $eventIds = $this->getEventIds($calculations);
        $unpaidEvents = $this->getUnpaidEvents($eventIds);

        $this->info("Found {$unpaidEvents->count()} entries to update.");

        $this->updateEventsWithAdPayData($unpaidEvents, $calculations, $exchangeRate);

        $ledgerEntries = $this->processExpenses($unpaidEvents, $exchangeRate);

        DB::commit();

        $this->info("Created {$ledgerEntries->count()} Ledger Entries.");
    }

    private function getEventIds(Collection $calculations): Collection
    {
        return $calculations->map(static function (array $amount) {
            return hex2bin($amount['event_id']);
        });
    }

    private function getExchangeRate(ExchangeRateReader $exchangeRateReader): ExchangeRate
    {
        $exchangeRate = $exchangeRateReader->fetchExchangeRate();
        $this->info(sprintf('Current exchange rate is %f', $exchangeRate->getValue()));

        return $exchangeRate;
    }

    private function getUnpaidEvents(Collection $eventIds): Collection
    {
        return EventLog::whereIn('event_id', $eventIds)
            ->whereNull('event_value_currency')
            ->get();
    }

    private function getCalculations(AdPay $adPay): Collection
    {
        $ts = $this->option('timestamp');
        $timestamp = $ts === null ? now()->subHour((int)$this->option('sub'))->getTimestamp() : (int)$ts;

        return new Collection($adPay->getPayments($timestamp, (bool)$this->option('force')));
    }

    private function updateEventsWithAdPayData(
        Collection $unpaidEvents,
        Collection $calculations,
        ExchangeRate $exchangeRate
    ): void {
        $unpaidEvents->each(static function (EventLog $entry) use ($calculations, $exchangeRate) {
            $calculation = $calculations->firstWhere('event_id', $entry->event_id);
            $amount = $calculation['amount'];

            $entry->event_value_currency = $amount;
            $entry->exchange_rate = $exchangeRate->getValue();
            $entry->event_value = $exchangeRate->toClick($amount);
            $entry->reason = $calculation['reason'];
        });
    }

    private function evaluateEventsByCampaign(Collection $unpaidEvents, ExchangeRate $exchangeRate): Collection
    {
        return $unpaidEvents->groupBy('campaign_id')
            ->mapToGroups(static function (Collection $events, string $campaignPublicId) use ($exchangeRate) {
                $campaign = Campaign::fetchByUuid($campaignPublicId);

                if (!$campaign) {
                    Log::warning(
                        sprintf(
                            '{"error":"no-campaign","command":"ops:adpay:payments:get","uuid":"%s"}',
                            $campaignPublicId
                        )
                    );

                    return new Collection();
                }

                $total = $campaign->budget;
                self::normalize($events, $total, $exchangeRate);

                return [$campaign->isDirectDeal() ? self::DIRECT : self::NORMAL => $events->all()];
            })->map(static function (Collection $groups) {
                return new Collection($groups->reduce('array_merge', []));
            });
    }

    private function processExpenses(Collection $unpaidEvents, ExchangeRate $exchangeRate): Collection
    {
        return $unpaidEvents->groupBy('advertiser_id')
            ->map(function (Collection $events, string $userPublicId) use ($exchangeRate) {
                $user = User::fetchByUuid($userPublicId);

                if (!$user) {
                    throw new Exception(
                        sprintf(
                            '{"error":"no-user","command":"ops:adpay:payments:get","advertiser_id":"%s"}',
                            $userPublicId
                        )
                    );
                }

                $clicks = $this->evaluateEventsByCampaign($events, $exchangeRate)
                    ->map(function (Collection $events, string $key) use ($user, $exchangeRate, $userPublicId) {
                        $userBalance = $key === self::DIRECT ? $user->getWalletBalance() : $user->getBalance();

                        if ($userBalance < 0) {
                            $this->error(sprintf(
                                'User %s has negative "%s" balance %d',
                                $userPublicId,
                                $key,
                                $userBalance
                            ));
                            $maxSpendableAmount = 0;
                        } else {
                            $maxSpendableAmount = $exchangeRate->fromClick($userBalance);
                        }

                        $insufficientFunds = self::normalize($events, $maxSpendableAmount, $exchangeRate);

                        $events->each(static function (EventLog $entry) {
                            $entry->save();
                        });

                        if ($insufficientFunds) {
                            Campaign::suspendAllForUserId($user->id);

                            Log::debug("Suspended Campaigns for user [{$user->id}] "
                                .'due to insufficient amount of clicks.');
                        }

                        return $events->sum(self::EVENT_VALUE);
                    });

                $maxBonus = $clicks->get(self::NORMAL, 0);

                $totalEventValueInClicks = $maxBonus + $clicks->get(self::DIRECT, 0);
                if ($totalEventValueInClicks > 0) {
                    return UserLedgerEntry::processAdExpense($user->id, $totalEventValueInClicks, $maxBonus);
                }

                return false;
            })->filter();
    }

    private static function normalize(Collection $events, int $maxSpendableAmount, ExchangeRate $exchangeRate): bool
    {
        $totalEventValue = $events->sum(self::EVENT_VALUE_CURRENCY);

        if ($maxSpendableAmount < $totalEventValue) {
            $normalizationFactor = (float)$maxSpendableAmount / $totalEventValue;
            $events->each(static function (EventLog $entry) use (
                $normalizationFactor,
                $exchangeRate
            ) {
                $amount = (int)floor($entry->event_value_currency * $normalizationFactor);
                $entry->event_value_currency = $amount;
                $entry->event_value = $exchangeRate->toClick($amount);
            });

            return true;
        }

        return false;
    }
}
