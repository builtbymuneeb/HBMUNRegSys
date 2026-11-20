<?php

namespace Database\Seeders;

use App\Models\ScheduleItem;
use Illuminate\Database\Seeder;

/**
 * The HBMUN '26 timetable (Day 1–3) as published on the schedule graphics.
 * Only fills an empty table, so running it twice does nothing.
 */
class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (ScheduleItem::exists()) {
            return;
        }

        $days = [
            1 => [
                ['10:00', '11:00', 'Opening Ceremony'],
                ['11:05', '14:00', 'Session 01'],
                ['14:00', '14:30', 'Break'],
                ['14:30', '17:30', 'Session 02'],
                ['17:30', '17:45', 'Break'],
                ['18:45', null, 'Social'],
            ],
            2 => [
                ['10:00', '12:00', 'Session 03'],
                ['12:00', '12:30', 'Break'],
                ['12:30', '14:30', 'Session 04'],
                ['14:30', '15:00', 'Break'],
                ['15:00', '18:00', 'Session 05'],
            ],
            3 => [
                ['16:00', '18:00', 'Award Ceremony'],
            ],
        ];

        foreach ($days as $day => $rows) {
            foreach ($rows as [$start, $end, $activity]) {
                ScheduleItem::create(['day' => $day, 'starts_at' => $start, 'ends_at' => $end, 'activity' => $activity]);
            }
        }
    }
}
