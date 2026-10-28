<?php

namespace Database\Factories;

use App\Models\CampusAmbassador;
use App\Support\ReferralCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampusAmbassador>
 */
class CampusAmbassadorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => ReferralCode::format(fake()->unique()->numberBetween(1, 999)),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('03#########'),
            'institution' => fake()->company().' College',
            'city' => fake()->city(),
        ];
    }
}
