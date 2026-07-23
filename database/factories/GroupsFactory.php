<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Groups>
 */
class GroupsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $public = $this->faker->randomElement(['0', '1', '2', '3']);

        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->name(),
            'creator_id' => $public,
            'is_public' => $public,
            'invitation_code' => $this->faker->regexify('[A-Z]{5}[0-4]{3}'),
            'max_members' => $this->faker->randomDigit(),
            'exact_score_points' => 10,
            'match_result_points' => 5,
            'correct_goal_points' => 3,
            'unique_exact_points' => 15,
            'multiplier_dieciseisavos' => 1.5,
            'multiplier_octavos' => 2.0,
            'multiplier_cuartos' => 3.0,
            'multiplier_semifinal' => 5.0,
            'multiplier_final' => 10.0,
            'created_at' => $this->faker->dateTimeThisDecade(),
        ];
    }
}
