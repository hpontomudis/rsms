<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the current Indonesian school year (July -> June) based on today's
 * date, and marks it as the single `is_current` academic year.
 */
class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $startYear = $now->month >= 7 ? $now->year : $now->year - 1;

        AcademicYear::query()->update(['is_current' => false]);

        AcademicYear::firstOrCreate(
            ['name' => "{$startYear}/".($startYear + 1)],
            [
                'start_date' => Carbon::createFromDate($startYear, 7, 1),
                'end_date' => Carbon::createFromDate($startYear + 1, 6, 30),
                'is_current' => true,
            ]
        )->update(['is_current' => true]);
    }
}
