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

use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\UserLedgerEntry;
use Illuminate\Support\Facades\DB;

class ExpiredWithdrawalCancellationCommand extends BaseCommand
{
    protected $signature = 'ops:expired-withdrawal:cancel';

    protected $description = 'Cancel expired withdrawal';

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $this->info('[ExpiredWithdrawalCancellation] Start command ' . $this->signature);

        $tokens = Token::fetchExpiredWithdrawals();

        DB::beginTransaction();

        $ids = [];
        foreach ($tokens as $token) {
            $ids[] = $token->toArray()['payload']['ledgerEntry'];

            if (!$token->multi_usage) {
                $token->delete();
            }
        }

        $updatedCount = UserLedgerEntry::where('type', UserLedgerEntry::TYPE_WITHDRAWAL)->where(
            'status',
            UserLedgerEntry::STATUS_AWAITING_APPROVAL
        )->whereIn('id', $ids)->update(['status' => UserLedgerEntry::STATUS_CANCELED]);

        DB::commit();

        $this->info(sprintf('[ExpiredWithdrawalCancellation] Cancelled %d expired withdrawal(s)', $updatedCount));
        $this->info('[ExpiredWithdrawalCancellation] Finish command ' . $this->signature);
    }
}
