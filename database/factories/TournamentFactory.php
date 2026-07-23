<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class TournamentFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

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
            'season' => $this->faker->word(),
            'country' => $this->faker->country('name'),
            'logo_url' => $this->faker->imageUrl(640, 480, 'flag', true),
            'flag_url' => $this->faker->imageUrl(640, 480, 'flag', true),
            'country_code' => $this->faker->countryCode(),
            'start_date' => $this->faker->dateTimeThisDecade(),
            'end_date' => $this->faker->dateTimeThisDecade(),
            'active' => $active,
        ];
    }
}
