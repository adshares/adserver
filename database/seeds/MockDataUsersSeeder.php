<?php

use Adshares\Adserver\Models\User;

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

        $data = MockDataSeeder::mockDataLoad(__DIR__ . '/../mock-data/users.json');
        $cols = array_flip($data->cols);
        $data = $data->data;

        $max = count($data) - 1;

        $selected = [];
        for ($c=0;$c<$this->limit;$c++) {
            $selected[] = MockDataSeeder::randomNoRepeat(0, $max, $selected);
        }

        DB::beginTransaction();
        foreach ($selected as $i) {
            $r = $data[$i];
            $u = new User;
            $u->email = $r[$cols['email']];
            $u->name = $r[$cols['name']];
            $u->password = $this->password;
            $u->save();

            $this->command->info(' Added - ' . $u->email);
        }
        DB::commit();

        $this->command->info('Users mock data seeded - all passwords = test1234');
    }
}
