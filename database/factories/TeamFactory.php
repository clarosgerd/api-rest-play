<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $active = $this->faker->randomElement(['0', '1']);

        return [
            'name' => $this->faker->name(),
            'country' => $this->faker->country('name'),
            'league' => $this->faker->company(),
            'logo_url' => $this->faker->imageUrl(640, 480, 'flag', true),
            'flag_url' => $this->faker->imageUrl(640, 480, 'flag', true),
            'country_code' => $this->faker->countryCode('name'),
            'active' => $active,
        ];
    }
}
