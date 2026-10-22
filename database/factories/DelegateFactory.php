<?php

namespace Database\Factories;

use App\Models\Committee;
use App\Models\Delegate;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delegate>
 */
class DelegateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'name' => fake()->name(),
            'cnic' => fake()->numerify('#####-#######-#'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('03#########'),
            'first_committee_id' => Committee::factory(),
            'second_committee_id' => Committee::factory(),
            'country_preference' => fake()->country(),
            'personality_preference' => null,
        ];
    }
}
