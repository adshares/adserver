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
use Adshares\Adserver\Mail\CampaignSuspension;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Conversion;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Exception\Exception;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Demand\Application\Service\AdPay;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use function floor;
use function now;
use function sprintf;

class AdPayGetPayments extends BaseCommand
{
    private const EVENT_VALUE_CURRENCY = 'event_value_currency';

    private const EVENT_VALUE = 'event_value';

    public const DIRECT = 'direct';

    public const NORMAL = 'normal';

    protected $signature = 'ops:adpay:payments:get
                            {--t|timestamp=}
                            {--s|sub=1}
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

        $exchangeRate = $this->getExchangeRate($exchangeRateReader);
        $allEvents = new Collection();
        $limit = (int)$this->option('chunkSize');
        $offset = 0;

        DB::beginTransaction();

        UserLedgerEntry::removeProcessingExpenses();

        do {
            $calculations = $this->getCalculations($adPay, $limit, $offset);

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

            $this->updateEventsWithAdPayData($unpaidEvents, $mappedCalculations, $exchangeRate);
            $this->updateConversionsWithAdPayData($unpaidConversions, $mappedCalculations, $exchangeRate);

            $allEvents = $allEvents->concat($unpaidEvents);

            $offset += $limit;
        } while ($limit === $calculations->count());

        $ledgerEntries = $this->processExpenses($allEvents, $exchangeRate);

        DB::commit();

        $this->info("Created {$ledgerEntries->count()} Ledger Entries.");
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

    private function getExchangeRate(ExchangeRateReader $exchangeRateReader): ExchangeRate
    {
        $exchangeRate = $exchangeRateReader->fetchExchangeRate();
        $this->info(sprintf('Current exchange rate is %f', $exchangeRate->getValue()));

        return $exchangeRate;
    }

    private function getCalculations(AdPay $adPay, int $limit, int $offset): Collection
    {
        $ts = $this->option('timestamp');
        $timestamp = $ts === null ? now()->subHour((int)$this->option('sub'))->getTimestamp() : (int)$ts;

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

    private function updateEventsWithAdPayData(
        Collection $unpaidEvents,
        array $mappedCalculations,
        ExchangeRate $exchangeRate
    ): void {
        $exchangeRateValue = $exchangeRate->getValue();

        $unpaidEvents->each(
            static function (EventLog $entry) use ($mappedCalculations, $exchangeRate, $exchangeRateValue) {
                $calculation = $mappedCalculations[$entry->event_id];
                $value = $calculation['value'];

                $entry->event_value_currency = $value;
                $entry->exchange_rate = $exchangeRateValue;
                $entry->event_value = $exchangeRate->toClick($value);
                $entry->payment_status = $calculation['status'];
            }
        );
    }

    private function updateConversionsWithAdPayData(
        Collection $unpaidConversions,
        array $mappedCalculations,
        ExchangeRate $exchangeRate
    ): void {
        $exchangeRateValue = $exchangeRate->getValue();

        $unpaidConversions->each(
            static function (Conversion $entry) use ($mappedCalculations, $exchangeRate, $exchangeRateValue) {
                $calculation = $mappedCalculations[$entry->uuid];
                $value = $calculation['value'];

                $entry->event_value_currency = $value;
                $entry->exchange_rate = $exchangeRateValue;
                $entry->event_value = $exchangeRate->toClick($value);
                $entry->payment_status = $calculation['status'];
            }
        );
    }

    private function evaluateEventsByCampaign(Collection $unpaidEvents, ExchangeRate $exchangeRate): Collection
    {
        return $unpaidEvents->groupBy('campaign_id')
            ->mapToGroups(static function (Collection $events, string $campaignPublicId) use ($exchangeRate) {
                Log::debug(sprintf('%s CampaignId %s', __FUNCTION__, $campaignPublicId));

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
                Log::debug(sprintf('%s UserId %s', __FUNCTION__, $userPublicId));

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
                    ->map(function (Collection $events, string $key) use ($user, $exchangeRate) {
                        $userBalance = $key === self::DIRECT ? $user->getWalletBalance() : $user->getBalance();

                        if ($userBalance < 0) {
                            $this->error(sprintf(
                                'User %s has negative "%s" balance %d',
                                $user->id,
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
                            if (Campaign::suspendAllForUserId($user->id) > 0) {
                                Log::debug(
                                    sprintf(
                                        'Suspended Campaigns for user [%s] due to insufficient amount of clicks.',
                                        $user->id
                                    )
                                );
                                Mail::to($user)->queue(new CampaignSuspension());
                            }
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
