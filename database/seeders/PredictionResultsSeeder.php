<?php

namespace Database\Seeders;

use App\Models\PredictionResults;
use Illuminate\Database\Seeder;

class PredictionResultsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        PredictionResults::factory(10)->create();
    }
}
