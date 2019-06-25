<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Illuminate\Database\Seeder;

class MockDataUserLedgerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->command->info('[mock] seeding: user ledger entries');

        if (0 == User::count()) {
            $this->command->error('Users must be seeded first');

            return 999;
        }

        $users = MockDataSeeder::mockDataLoad('user-ledger.json');

        DB::beginTransaction();
        foreach ($users as $user1) {
            $user = User::where('email', $user1->email)->first();
            if (empty($user)) {
                DB::rollback();
                throw new Exception("User not found <{$user1->email}>");
            }

            $userId = $user->id;

            foreach ($user1->txs as $tx) {
                $tx->user_id = $userId;
                factory(UserLedgerEntry::class)->create((array) $tx);
            }
            $this->command->info(" Added - txs for user <{$user->email}>");
        }
        DB::commit();

        $this->command->info('[mock] seeding: user ledger entries [done]');
    }
}
