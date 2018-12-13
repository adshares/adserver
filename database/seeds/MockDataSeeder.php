<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
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
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

use Illuminate\Database\Seeder;

class MockDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->call(MockDataUsersSeeder::class);
        $this->call(MockDataUserLedgerSeeder::class);
        $this->call(MockDataSitesSeeder::class);
        $this->call(MockDataCampaignsSeeder::class);
        $this->call(MockDataNetworkHostsSeeder::class);
        $this->call(MockDataPaymentsAndEventLogsSeeder::class);
    }

    public static function randomNoRepeat($min, $max, $exclude)
    {
        do {
            $i = rand($min, $max);
        } while (in_array($i, $exclude));

        return $i;
    }

    public static function mockDataLoad($file)
    {
        $json = file_get_contents(__DIR__.'/../mock-data/'.$file);
        if (empty($json)) {
            throw new \Exception("Error loading mock-data/$file");
        }
        $json = json_decode($json);
        if (empty($json)) {
            throw new \Exception("Error processing mock-data/$file");
        }

        return $json;
    }
}
