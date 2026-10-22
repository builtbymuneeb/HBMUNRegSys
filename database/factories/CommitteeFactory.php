<?php

namespace Database\Factories;

use App\Enums\PortfolioType;
use App\Models\Committee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Committee>
 */
class CommitteeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('???'),
            'name' => fake()->words(3, true),
            'portfolio_type' => PortfolioType::Country,
            'sort_order' => 0,
        ];
    }

    /**
     * A committee whose delegates represent characters rather than countries.
     */
    public function characters(): static
    {
        return $this->state(fn (array $attributes) => [
            'portfolio_type' => PortfolioType::Character,
        ]);
    }
}
