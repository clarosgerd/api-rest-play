<?php

namespace Database\Seeders;

use App\Models\GroupPredictions;
use Illuminate\Database\Seeder;

class GroupPredictionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        GroupPredictions::factory()
            ->count(10)
            ->create();
    }
}
