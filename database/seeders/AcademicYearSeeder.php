<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Services\AcademicYearService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the current Indonesian school year (July -> June) based on today's
 * date, and marks it as the single `is_current` academic year via
 * AcademicYearService::setCurrent() -- the same canonical transition every
 * other caller uses (Foundation F1). Re-running this seeder is idempotent:
 * firstOrCreate finds the existing row, and setCurrent() no-ops when that
 * row is already current.
 */
class AcademicYearSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $startYear = $now->month >= 7 ? $now->year : $now->year - 1;

        $year = AcademicYear::firstOrCreate(
            ['name' => "{$startYear}/".($startYear + 1)],
            [
                'start_date' => Carbon::createFromDate($startYear, 7, 1),
                'end_date' => Carbon::createFromDate($startYear + 1, 6, 30),
                'is_current' => false,
            ]
        );

        app(AcademicYearService::class)->setCurrent($year);
    }
}
