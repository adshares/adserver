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
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DemandBlockRequiredAmount extends Command
{
    use LineFormatterTrait;

    protected $signature = 'ops:demand:payments:block';

    public function handle(): void
    {
        $this->info('Start command '.$this->signature);

        $blockade = Campaign::where('status', Campaign::STATUS_ACTIVE)
            ->groupBy('user_id')
            ->selectRaw('sum(budget) as sum, user_id')
            ->pluck('sum', 'user_id');

        DB::beginTransaction();

        UserLedgerEntry::removeBockade();

        $blockade->each(function ($sum, $userId) {
            UserLedgerEntry::construct(
                $userId,
                -$sum,
                UserLedgerEntry::STATUS_PENDING,
                UserLedgerEntry::TYPE_AD_EXPENDITURE
            )->save();
        });

        DB::commit();

        Log::info('Created '.count($blockade).' blocking Ledger Entries.');
    }
}
