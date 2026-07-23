<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupBets>
 */
class GroupBetsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // $isadmin=$this->faker->randomElement(['0', '1']);
        return [
            'group_id' => \App\Models\Groups::factory(),
            'bet_id' => \App\Models\Bets::factory(),
            'created_by' => \App\Models\User::factory(),
            'created_at' => $this->faker->dateTimeThisDecade(),
        ];
    }
}
