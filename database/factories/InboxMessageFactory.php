<?php

namespace Database\Factories;

use App\Models\InboxMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboxMessage>
 */
class InboxMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'direction' => InboxMessage::IN,
            'message_id' => '<'.fake()->unique()->uuid().'@mail.example.com>',
            'email' => fake()->safeEmail(),
            'name' => fake()->name(),
            'subject' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'received_at' => now(),
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => now()]);
    }
}
