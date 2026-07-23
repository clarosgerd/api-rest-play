<?php

namespace Database\Factories;

use App\Models\Groups;
use App\Models\Predictions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupPredictions>
 */
class GroupPredictionsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Groups::factory(),
            'prediction_id' => Predictions::factory(),
            'points_awarded' => $this->faker->randomDigit(),
        ];
    }
}
