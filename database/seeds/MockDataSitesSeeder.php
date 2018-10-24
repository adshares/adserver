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

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Illuminate\Database\Seeder;

class MockDataSitesSeeder extends Seeder
{
    private $sizes = [
        #best
        "medium rectangle" => "300x250",
        "large rectangle" => "336x280",
        "leaderboard" => "728x90",
        "half page" => "300x600",
        "large mobile banner" => "320x100",
        #other
        "banner" => "468x60",
        "half banner" => "234x60",
        "button" => "125x125",
        "skyscraper" => "120x600",
        "wide skyscraper" => "160x600",
        "small rectangle" => "180x150",
        "vertical banner" => "120x240",
        "small square" => "200x200",
        "portrait" => "300x1050",
        "square" => "250x250",
        "mobile banner" => "320x50",
        "large leaderboard" => "970x90",
        "billboard" => "970x250",
        #polish
        "billboard" => "750x100",
        "double billboard" => "750x200",
        "tripple billboard" => "750x300",
    ];
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
            'width' => 230,
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

        $sites_data = MockDataSeeder::mockDataLoad('sites-publishers.json');

        DB::beginTransaction();
        foreach ($sites_data as $r) {
            $u = User::where('email', $r->email)->first();
            if (empty($u)) {
                DB::rollback();
                throw new Exception("User not found <{$r->email}>");
            }

            foreach ($r->sites as $rs) {
                $s = new Site();
                $s->name = $rs->name;
                $s->status = $rs->status;
                $s->site_requires = isset($rs->site_requires) ? json_encode($rs->site_requires) : null;
                $s->site_excludes = isset($rs->site_excludes) ? json_encode($rs->site_excludes) : null;

                $s->user_id = $u->id;
                $s->save();
                foreach ($this->zones as $zn => $zr) {
                    $z = new Zone();
                    $z->site_id = $s->id;
                    $z->name = $zn;
                    $z->width = $zr['width'];
                    $z->height = $zr['height'];
                    $z->save();
                }
                $this->command->info(" Added - [$s->name] for user <{$u->email}>");
            }
        }
        DB::commit();

        $this->command->info('[mock] seeding: sites [done]');
    }
}
