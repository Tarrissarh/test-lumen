<?php

use App\Email;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Email::create(
            [
                'email'       => 'text@example.com',
                'isConfirmed' => false,
            ]
        );
    }
}
