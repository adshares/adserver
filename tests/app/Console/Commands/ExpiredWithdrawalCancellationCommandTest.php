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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use DateTime;

class ExpiredWithdrawalCancellationCommandTest extends ConsoleTestCase
{
    public function testCancelExpiredWithdrawalEmpty(): void
    {
        $this->artisan('ops:expired-withdrawal:cancel')->assertExitCode(0);
    }

    public function testCancelExpiredWithdrawal(): void
    {
        $user = factory(User::class)->create();

        $addressFrom = '0002-00000008-F4B5';
        $addressTo = '0001-00000001-8B4E';
        $amount = 1000000000;
        $total = 1001000000;

        $ledgerEntry = UserLedgerEntry::construct(
            $user->id,
            -$total,
            UserLedgerEntry::STATUS_AWAITING_APPROVAL,
            UserLedgerEntry::TYPE_WITHDRAWAL
        )->addressed($addressFrom, $addressTo);
        $ledgerEntry->save();

        $userLedgerEntryId = $ledgerEntry->id;

        $payload = [
            'request' => [
                'to' => $addressTo,
                'amount' => $amount,
                'memo' => null,
            ],
            'ledgerEntry' => $userLedgerEntryId,
        ];
        $token = Token::generate(Token::EMAIL_APPROVE_WITHDRAWAL, $user, $payload);
        $token->valid_until = new DateTime('-1 minute');
        $token->save();

        $this->artisan('ops:expired-withdrawal:cancel')->assertExitCode(0);

        self::assertCount(0, Token::all());

        $ule = UserLedgerEntry::find($userLedgerEntryId);
        self::assertEquals(UserLedgerEntry::STATUS_CANCELED, $ule->status);
    }
}
