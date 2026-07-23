<?php

namespace Database\Seeders;

use App\Models\GroupBets;
use Illuminate\Database\Seeder;

class GroupBetsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        GroupBets::factory()
            ->count(10)
            ->create();
    }
}
