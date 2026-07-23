<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Matches>
 */
class MatchesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = $this->faker->randomElement(['scheduled', 'live', 'finished', 'canceled']);
        $score = $this->faker->randomElement(['0', '1', '2', '3']);

        return [
            'home_team_id' => \App\Models\Team::factory(),
            'away_team_id' => \App\Models\Team::factory(),
            'tournament_id' => \App\Models\Tournament::factory(),
            'stage_id' => \App\Models\TournamentStage::factory(),
            'match_date' => $this->faker->dateTimeThisDecade(),
            'status' => $status,
            'home_score' => $score,
            'away_score' => $score,
        ];
    }
}
