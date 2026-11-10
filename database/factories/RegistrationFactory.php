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
            'type' => RegistrationType::PrivateDelegation,
            'status' => RegistrationStatus::Pending,
            'organisation_name' => fake()->lastName().' Delegation',
            'city' => fake()->city(),
            'contact_name' => fake()->name(),
            'contact_cnic' => fake()->numerify('#####-#######-#'),
            'contact_phone' => fake()->numerify('03#########'),
            'contact_email' => fake()->unique()->safeEmail(),
            'amount_due' => RegistrationType::PrivateDelegation->amountDueFor(3),
            'payment_screenshot_path' => 'payment-screenshots/'.fake()->uuid().'.png',
        ];
    }

    /**
     * Give every registration a screenshot row for its first screenshot, as a real submission has.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Registration $registration) {
            $registration->paymentScreenshots()->create(['path' => $registration->payment_screenshot_path]);
        });
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

    /**
     * A Homebridge student registering on their own.
     */
    public function privateDelegate(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RegistrationType::PrivateDelegate,
            'organisation_name' => 'Homebridge '.fake()->city().' campus',
            'amount_due' => RegistrationType::PrivateDelegate->amountDueFor(1),
        ])->has(Delegate::factory()->count(1));
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
            'amount_paid' => $attributes['amount_due'] ?? null,
            'reviewed_at' => now(),
        ]);
    }
}
