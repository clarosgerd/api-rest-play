<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Odds>
 */
class OddsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bet = $this->faker->randomElement(['1X2', 'over_under', 'handicap', 'both_teams_score', 'correct_score']);

        return [
            'match_id' => \App\Models\Matches::factory(),
            'bet_type' => $bet,
            'option' => $this->faker->word(50),
            'value' => $this->faker->randomFloat(1, 20, 30),
            'last_update' => $this->faker->dateTimeThisDecade(),

        ];
    }
}
