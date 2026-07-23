<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Predictions>
 */
class PredictionsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $predicted_result = $this->faker->randomElement(['home_win', 'away_win', 'draw']);
        $bet = $this->faker->randomElement(['exact_score', 'match_result', 'both_teams_score']);

        return [
            'user_id' => \App\Models\User::factory(),
            'match_id' => \App\Models\Matches::factory(),
            'predicted_home_score' => $this->faker->numberBetween(0, 10),
            'predicted_away_score' => $this->faker->numberBetween(0, 10),
            'predicted_result' => $predicted_result,
            'bet_type' => $bet,
            'confidence' => $this->faker->numberBetween(0, 100),
            'created_at' => $this->faker->dateTimeThisDecade(),
            'is_public' => $this->faker->numberBetween(0, 1),

        ];
    }
}
