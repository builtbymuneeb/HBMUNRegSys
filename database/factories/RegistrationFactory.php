<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\Delegate;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => RegistrationType::PrivateDelegate,
            'status' => RegistrationStatus::Pending,
            'organisation_name' => null,
            'city' => fake()->city(),
            'contact_name' => fake()->name(),
            'contact_cnic' => fake()->numerify('#####-#######-#'),
            'contact_phone' => fake()->numerify('03#########'),
            'contact_email' => fake()->unique()->safeEmail(),
            'amount_due' => 1000,
            'payment_screenshot_path' => 'payment-screenshots/'.fake()->uuid().'.png',
        ];
    }

    /**
     * An institution delegation with the given number of delegates.
     */
    public function institutionDelegation(int $delegates = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RegistrationType::InstitutionDelegation,
            'organisation_name' => fake()->company().' College',
            'amount_due' => RegistrationType::InstitutionDelegation->amountDueFor($delegates),
        ])->has(Delegate::factory()->count($delegates));
    }

    public function rejected(string $reason = 'Screenshot is unreadable.'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Rejected,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Verified,
            'reviewed_at' => now(),
        ]);
    }
}
