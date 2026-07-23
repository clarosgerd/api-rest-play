<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transactions>
 */
class TransactionsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['deposit', 'withdrawal', 'bet', 'win', 'bonus', 'refund']);
        $status = $this->faker->randomElement(['pending', 'completed', 'failed']);

        return [
            'user_id' => \App\Models\User::factory(),
            'type' => $type,
            'amount' => $this->faker->randomFloat(1, 20, 30),
            'status' => $status,
            'reference' => $this->faker->word(),
            'processed_at' => $this->faker->dateTimeThisDecade(),
        ];
    }
}
