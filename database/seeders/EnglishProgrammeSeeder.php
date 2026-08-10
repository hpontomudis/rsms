<?php

namespace Database\Seeders;

use App\Models\EnglishLevel;
use App\Models\EnglishProgramme;
use App\Models\EnglishProgrammeGrade;
use App\Models\Grade;
use Illuminate\Database\Seeder;

/**
 * Rahai's two English proficiency frameworks.
 *
 * All of it is DATA: the programmes, their level ladders, and which grades
 * each applies to. Nothing in application code should branch on "primary" or
 * "junior high" -- consumers read these rows.
 *
 * Senior High (Year 10-12) and Kindergarten are intentionally absent: they run
 * no proficiency programme, and English there is an ordinary class-based
 * subject.
 */
class EnglishProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $programmes = [
            [
                'name' => 'Primary English Programme',
                'code' => 'PRI-ENG',
                'description' => 'Proficiency-based English levels for primary grades.',
                'levels' => ['Purple', 'Pink', 'Gold', 'Green', 'Blue', 'Red'],
                'grades' => ['Year 1', 'Year 2', 'Year 3', 'Year 4', 'Year 5', 'Year 6'],
            ],
            [
                'name' => 'Junior High English Programme',
                'code' => 'JHS-ENG',
                'description' => 'Proficiency-based English levels for junior high grades.',
                'levels' => ['Level A', 'Level B', 'Level C'],
                'grades' => ['Year 7', 'Year 8', 'Year 9'],
            ],
        ];

        foreach ($programmes as $definition) {
            $programme = EnglishProgramme::firstOrCreate(
                ['name' => $definition['name']],
                [
                    'code' => $definition['code'],
                    'description' => $definition['description'],
                    'status' => 'active',
                ]
            );

            foreach ($definition['levels'] as $index => $levelName) {
                EnglishLevel::firstOrCreate(
                    ['english_programme_id' => $programme->id, 'name' => $levelName],
                    ['sequence' => $index + 1, 'status' => 'active']
                );
            }

            foreach ($definition['grades'] as $gradeName) {
                $grade = Grade::where('name', $gradeName)->first();

                if (! $grade) {
                    continue;
                }

                // Written through the model (not attach()) so the link is
                // audited -- see EnglishProgrammeGrade.
                EnglishProgrammeGrade::firstOrCreate([
                    'grade_id' => $grade->id,
                ], [
                    'english_programme_id' => $programme->id,
                ]);
            }
        }
    }
}
