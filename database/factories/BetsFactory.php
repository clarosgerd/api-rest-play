<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BetsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $public = $this->faker->randomElement(['0', '1', '2', '3']);
        $status = $this->faker->randomElement(['pending', 'won', 'lost', 'refunded']);

        return [
            'user_id' => \App\Models\User::factory(),
            'match_id' => \App\Models\Matches::factory(),
            'odd_id' => \App\Models\Odds::factory(),
            'amount' => $this->faker->randomFloat(1, 20, 30),
            'potential_payout' => $this->faker->randomFloat(1, 20, 30),
            'status' => $status,
            'placed_at' => $this->faker->dateTimeThisDecade(),
        ];
    }
}
