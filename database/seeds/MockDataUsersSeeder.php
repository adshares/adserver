<?php

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserAdserverWallet;
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
        foreach ($data as $r) {
            $u = new User();
            $u->email = $r->email;
            $u->password = $r->password;
            $u->save();

            $w = UserAdserverWallet::where('user_id', $u->id)->first();
            $w->adshares_address = $r->adserverWallet->adshares_address;
            $w->total_funds = $r->adserverWallet->total_funds;
            $w->save();

            $this->command->info(" Added - <{$u->email}> with password '{$r->password}'");
        }
        DB::commit();

        $this->command->info('[mock] seeding: users from users.json - DONE');
    }
}
