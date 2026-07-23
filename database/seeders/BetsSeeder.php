<?php

namespace Database\Seeders;

use App\Models\Bets;
use Illuminate\Database\Seeder;

class BetsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Bets::factory()
            ->count(4)
            ->hasUser(6)
            ->create();
    }
}
