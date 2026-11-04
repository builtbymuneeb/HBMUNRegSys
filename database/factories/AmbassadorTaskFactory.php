<?php

namespace Database\Factories;

use App\Enums\TaskAudience;
use App\Models\AmbassadorTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AmbassadorTask>
 */
class AmbassadorTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'points' => 10,
            'audience' => TaskAudience::Everyone,
            'repeatable' => false,
        ];
    }

    public function repeatable(?int $max = null): static
    {
        return $this->state(fn () => ['repeatable' => true, 'max_completions' => $max]);
    }
}
