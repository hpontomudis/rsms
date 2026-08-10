<?php

namespace Database\Seeders;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

/**
 * Rahai currently reports on two semesters. These are seeded as DATA, not
 * baked into application logic -- a year needing a different number or naming
 * of periods only needs different rows here (or entered through the UI later),
 * with no code change.
 *
 * The date split is derived from the academic year's own span rather than
 * assuming a calendar shape.
 */
class AcademicPeriodSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AcademicYear::all() as $year) {
            if ($year->periods()->exists()) {
                continue;
            }

            $start = $year->start_date->copy();
            $end = $year->end_date->copy();

            // Midpoint split: first period runs to the halfway mark, second
            // picks up the next day.
            $midpoint = $start->copy()->addDays((int) floor($start->diffInDays($end) / 2));

            AcademicPeriod::create([
                'academic_year_id' => $year->id,
                'name' => 'Semester 1',
                'sequence' => 1,
                'start_date' => $start,
                'end_date' => $midpoint,
            ]);

            AcademicPeriod::create([
                'academic_year_id' => $year->id,
                'name' => 'Semester 2',
                'sequence' => 2,
                'start_date' => $midpoint->copy()->addDay(),
                'end_date' => $end,
            ]);
        }
    }
}
