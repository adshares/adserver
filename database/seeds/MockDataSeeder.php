<?php

use Illuminate\Database\Seeder;

class MockDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call(MockDataUsersSeeder::class);
        $this->call(MockDataWebsitesSeeder::class);
        $this->call(MockDataCampaignsSeeder::class);
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
        $json = file_get_contents($file);
        if (empty($json)) {
            $this->command->error('Error loading mock-data/users.json');
            throw new \Exception;
        }
        $json = json_decode($json);
        if (empty($json)) {
            $this->command->error('Error processing mock-data/users.json');
            throw new \Exception;
        }
        return $json;
    }
}
