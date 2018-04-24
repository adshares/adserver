<?php

use App\User;
use App\Website;
use App\WebsiteExclude;
use App\WebsiteRequire;
use App\Zone;

use Illuminate\Database\Seeder;

class MockDataWebsitesSeeder extends Seeder
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
        'width' => 160,
        'height' => 600,
      ],
      'bottom' => [
        'width' => 728,
        'height' => 90,
      ],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('[mock] seeding: websites');

        if (Website::count() > 0) {
            $this->command->error('Websites already seeded');
            return 999;
        }

        $max = User::count();
        if (empty($max)) {
            $this->command->error('Users must be seeded first');
        }

        $webp_data = MockDataSeeder::mockDataLoad(__DIR__ . '/../mock-data/websites-publishers.json');
        $webp_cols = array_flip($webp_data->cols);
        $webp_data = $webp_data->data;

        $max = count($webp_data) - 1;

        DB::beginTransaction();
        foreach ($webp_data as $i => $r) {
            $w = new Website;
            $w->host = $r[$webp_cols['url']];
            $w->user_id = $i+1;
            $w->save();

            foreach ($this->zones as $zn => $zr) {
                $z = new Zone;
                $z->website_id = $w->id;
                $z->name = $zn;
                $z->width = $zr['width'];
                $z->height = $zr['height'];
                $z->save();
            }
        }
        DB::commit();

        $this->command->info('Websites mock data seeded - for first ' . ($i+1) . ' users');
    }
}
