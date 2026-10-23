<?php

namespace Database\Factories;

use App\Models\Committee;
use App\Models\CommitteeSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<CommitteeSession>
 */
class CommitteeSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::parse('2026-11-21 10:00', CommitteeSession::TIMEZONE)->utc();

        return [
            'committee_id' => Committee::factory(),
            'title' => 'Session '.fake()->numberBetween(1, 6),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'meeting_url' => 'https://meet.google.com/'.fake()->lexify('???-????-???'),
        ];
    }
}
