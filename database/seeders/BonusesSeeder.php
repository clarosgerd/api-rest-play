<?php

namespace Database\Seeders;

use App\Models\Bonuses;
use Illuminate\Database\Seeder;

class BonusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        Bonuses::factory()
            ->count(10)
            ->create();
    }
}
