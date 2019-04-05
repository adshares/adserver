<?php declare(strict_types = 1);
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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\UserLedgerEntry;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DemandBlockRequiredAmount extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:demand:payments:block';

    public function handle(): void
    {
        $this->info('Start command '.$this->signature);

        DB::beginTransaction();

        UserLedgerEntry::pushBlockedToProcessing();

        $blockade = $this->fetchRequiredBudgetsPerUser();
        $this->info('Attempt to create '.count($blockade).' blockades.');
        $this->blockAmountOrSuspendCampaigns($blockade);

        DB::commit();

        $this->info('Created '.count($blockade).' new blocking Ledger entries.');
    }

    private function fetchRequiredBudgetsPerUser(): Collection
    {
        $query = Campaign::where('status', Campaign::STATUS_ACTIVE)->where(
            function ($q) {
                $dateTime = self::getDateTimeRoundedToNextHour();
                $q->where('time_end', '>=', $dateTime)->orWhere('time_end', null);
            }
        );

        $blockade = $query->groupBy('user_id')->selectRaw('sum(budget) as sum, user_id')->pluck('sum', 'user_id');

        return $blockade;
    }

    private function blockAmountOrSuspendCampaigns(Collection $blockade): void
    {
        $blockade->each(function ($sum, $userId) {
            try {
                UserLedgerEntry::block(UserLedgerEntry::TYPE_AD_EXPENSE, (int)$userId, (int)$sum);
            } catch (InvalidArgumentException $e) {
                Log::warning($e->getMessage());

                Campaign::suspendAllForUserId($userId);
            }
        });
    }

    private static function getDateTimeRoundedToNextHour(): DateTime
    {
        $date = new DateTime();
        $minutes = (int)$date->format('i');
        if ($minutes > 0) {
            $date->modify('+1 hour');
            $date->modify('-'.$minutes.' minutes');
        }

        return $date;
    }
}
