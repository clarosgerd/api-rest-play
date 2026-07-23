<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupMembers>
 */
class GroupMembersFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isadmin = $this->faker->randomElement(['0', '1']);

        return [
            'group_id' => \App\Models\Groups::factory(),
            'user_id' => \App\Models\User::factory(),
            'is_admin' => $isadmin,
            'joined_at' => $this->faker->dateTimeThisDecade(),
        ];
    }
}
