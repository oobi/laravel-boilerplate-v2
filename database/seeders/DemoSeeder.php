<?php

namespace Database\Seeders;

use Database\Seeders\Demo\UserSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // system users
            UserSeeder::class,
        ]);
    }
}
