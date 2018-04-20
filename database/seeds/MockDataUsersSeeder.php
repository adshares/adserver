<?php

use App\User;

use Illuminate\Database\Seeder;

class MockDataUsersSeeder extends Seeder
{
    protected $password = 'test1234';
    protected $limit = 20;
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->command->info('[mock] seeding: users');

        if (User::count() > 0) {
            $this->command->error('Users already seeded');
            return 999;
        }

        $data = $this->mockDataLoad(__DIR__ . '/../mock-data/users.json');
        $cols = array_flip($data->cols);
        $data = $data->data;
        // print_r($data);
        // print_r($cols);

        $max = count($data) - 1;

        $selected = [];
        for ($c=0;$c<$this->limit;$c++) {
            $selected[] = $this->randomNoRepeat(0, $max, $selected);
        }

        DB::beginTransaction();
        foreach ($selected as $i) {
            $r = $data[$i];
            $u = new User;
            $u->email = $r[$cols['email']];
            $u->name = $r[$cols['name']];
            $u->password = $this->password;
            $u->save();
        }
        DB::commit();

        $this->command->info('Users mock data seeded');
    }

    protected function randomNoRepeat($min, $max, $exclude)
    {
        do {
            $i = rand($min, $max);
        } while (in_array($i, $exclude));
        return $i;
    }

    protected function mockDataLoad($file)
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
