<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bonuses>
 */
class BonusesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['welcome', 'deposit', 'loyalty', 'promo']);

        return [
            'user_id' => \App\Models\User::factory(),
            'type' => $type,
            'amount' => $this->faker->randomFloat(1, 20, 30),
            'expires_at' => $this->faker->dateTimeThisDecade(),
            'is_used' => $this->faker->boolean(0.5),
        ];
    }
}
