<?php

namespace Database\Seeders;

use App\Models\TournamentStage;
use Illuminate\Database\Seeder;

class TournamentStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        TournamentStage::factory()->count(10)->hasTournament(2)->create();
    }
}
