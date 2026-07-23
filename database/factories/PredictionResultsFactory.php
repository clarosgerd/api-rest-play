<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PredictionResults>
 */
class PredictionResultsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prediction_id' => \App\Models\Predictions::factory(),
            'is_correct' => $this->faker->boolean(0.5),
            'points_earned' => $this->faker->randomDigit(),
            'processed_at' => $this->faker->dateTimeThisDecade(),
        ];
    }
}
