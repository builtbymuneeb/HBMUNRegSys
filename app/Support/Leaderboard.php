<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\CampusAmbassador;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Ranks campus ambassadors by points. HB and Private ambassadors share one board.
 *
 * Points are task points (approved submissions) + referral points (verified
 * delegates × the current rate) + any points staff added or took away by hand.
 * Referral points follow the rate, so changing it updates everyone fairly.
 */
class Leaderboard
{
    /**
     * @return Collection<int, array{rank: int, ambassador: CampusAmbassador, task_points: int, referral_points: int, bonus_points: int, verified_delegates: int, tasks_done: int, total: int}>
     */
    public static function standings(): Collection
    {
        $rate = Setting::pointsPerVerifiedDelegate();

        $rows = CampusAmbassador::query()
            ->withReferralCounts()
            ->withSum(['submissions as task_points' => fn ($query) => $query->where('status', SubmissionStatus::Approved)], 'points_awarded')
            ->withCount(['submissions as tasks_done' => fn ($query) => $query->where('status', SubmissionStatus::Approved)])
            ->withSum('pointAdjustments as bonus_points', 'points')
            ->get()
            ->map(function (CampusAmbassador $ambassador) use ($rate) {
                $task = (int) $ambassador->task_points;
                $bonus = (int) $ambassador->bonus_points;
                $referral = (int) $ambassador->verified_delegates_count * $rate;

                return [
                    'rank' => 0,
                    'ambassador' => $ambassador,
                    'task_points' => $task,
                    'referral_points' => $referral,
                    'bonus_points' => $bonus,
                    'verified_delegates' => (int) $ambassador->verified_delegates_count,
                    'tasks_done' => (int) $ambassador->tasks_done,
                    'total' => $task + $referral + $bonus,
                ];
            })
            ->sort(fn (array $a, array $b) => [$b['total'], $a['ambassador']->name] <=> [$a['total'], $b['ambassador']->name])
            ->values();

        // Equal points share a rank: 1, 2, 2, 4.
        $ranks = [];
        $previous = null;
        $rank = 0;

        foreach ($rows->values() as $index => $row) {
            if ($row['total'] !== $previous) {
                $rank = $index + 1;
                $previous = $row['total'];
            }
            $ranks[] = $rank;
        }

        return $rows->map(fn (array $row, int $index): array => [...$row, 'rank' => $ranks[$index]]);
    }

    /**
     * One ambassador's row, or null if they are not on the board.
     *
     * @return array{rank: int, ambassador: CampusAmbassador, task_points: int, referral_points: int, bonus_points: int, verified_delegates: int, tasks_done: int, total: int}|null
     */
    public static function standingOf(CampusAmbassador $ambassador): ?array
    {
        return self::standings()->first(fn (array $row) => $row['ambassador']->is($ambassador));
    }
}
