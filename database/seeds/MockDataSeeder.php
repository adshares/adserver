<?php

use Illuminate\Database\Seeder;

class MockDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $this->call(MockDataUsersSeeder::class);
        $this->call(MockDataSitesSeeder::class);
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
