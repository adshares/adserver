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
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Illuminate\Database\Seeder;

class MockDataSitesSeeder extends Seeder
{
    private $zones = [
        'top' => [
            'width' => 728,
            'height' => 90,
        ],
        'left' => [
            'width' => 160,
            'height' => 600,
        ],
        'right' => [
            'width' => 300,
            'height' => 600,
        ],
        'mid' => [
            'width' => 750,
            'height' => 300,
        ],
        'bottom' => [
            'width' => 728,
            'height' => 90,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->command->info('[mock] seeding: sites');

        if (Site::count() > 0) {
            $this->command->error('Sites table not empty - seeding only into empty db');

            return 999;
        }

        if (0 == User::count()) {
            $this->command->error('Users must be seeded first');

            return 999;
        }

        $publishers = MockDataSeeder::mockDataLoad('sites-publishers.json');

        DB::beginTransaction();
        foreach ($publishers as $publisher) {
            $user = User::where('email', $publisher->email)->first();
            if (empty($user)) {
                DB::rollback();
                throw new Exception("User not found <{$publisher->email}>");
            }

            foreach ($publisher->sites as $site) {
                $newSite = factory(Site::class)->create([
                    'user_id' => $user->id,
                    'name' => $site->name,
                    'status' => $site->status,
                    'site_requires' => isset($site->site_requires) ? json_encode($site->site_requires) : null,
                    'site_excludes' => isset($site->site_excludes) ? json_encode($site->site_excludes) : null,
                ]);

                $zones = isset($site->zones) ? json_decode(json_encode($site->zones), true) : $this->zones;

                foreach ($zones as $zoneNames => $zone) {
                    factory(Zone::class)->create([
                        'name' => $zoneNames,
                        'site_id' => $newSite->id,
                        'width' => $zone['width'],
                        'height' => $zone['height'],
                    ]);
                }

                $this->command->info(" Added - [$newSite->name] for user <{$user->email}>");
            }
        }
        DB::commit();

        $this->command->info('[mock] seeding: sites [done]');
    }
}
