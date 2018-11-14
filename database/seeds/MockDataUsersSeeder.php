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

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserAdserverWallet;
use Adshares\Adserver\Models\UserLedgerEntry;
use Illuminate\Database\Seeder;

class MockDataUsersSeeder extends Seeder
{
    protected $limit = 20;

    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->command->info('[mock] seeding: users from users.json');

        if (User::count() > 0) {
            $this->command->error('Users table not empty - seeding only into empty db');

            return 999;
        }

        $data = MockDataSeeder::mockDataLoad('users.json');

        DB::beginTransaction();
        foreach ($data as $row) {
            $user = new User();
            $user->email = $row->email;
            $user->password = $row->password;
            $user->is_admin = $row->isAdmin ?? false;
            $user->is_advertiser = $row->isAdvertiser ?? false;
            $user->is_publisher = $row->isPublisher ?? false;
            if ($row->isConfirmed ?? false) {
                $user->email_confirmed_at = date('Y-m-d H:i:s');
            }
            $user->save();

            if (isset($row->adserverWallet)) {
                $wallet = UserAdserverWallet::where('user_id', $user->id)->first();
                $wallet->adshares_address = $row->adserverWallet->adshares_address;
                $wallet->total_funds = $row->adserverWallet->total_funds;
                $wallet->save();

                $ledgerEntry = UserLedgerEntry::make([
                    'user_id' => $user->id,
                    'amount' => $row->adserverWallet->total_funds * (10 ** 11),
                    'status' => UserLedgerEntry::STATUS_ACCEPTED,
                    '' => $row->adserverWallet->adshares_address,
                    'address_from' => '',
                    'address_to' => '',
                ]);
            }

            $this->command->info(" Added - <{$user->email}> with password '{$row->password}'");
        }
        DB::commit();

        $this->command->info('[mock] seeding: users from users.json - DONE');
    }
}
