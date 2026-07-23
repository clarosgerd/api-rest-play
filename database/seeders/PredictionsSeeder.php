<?php

namespace Database\Seeders;

use App\Models\Predictions;
use Illuminate\Database\Seeder;

class PredictionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Predictions::factory(10)->create();
    }
}
