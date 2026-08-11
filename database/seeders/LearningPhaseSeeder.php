<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\LearningPhase;
use App\Models\LearningPhaseGrade;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The national Learning Phase structure and which grades sit in each.
 *
 * Approved reference structure, so it IS seeded -- unlike curricula, where
 * inventing a version label and an effective date just to make the table
 * non-empty would be fabricating a school decision nobody has made.
 *
 * Grades are resolved by their real seeded names ("Kindergarten 1", not
 * "KG1"). A missing grade throws rather than silently skipping: a phase
 * quietly missing half its grades is exactly the kind of damage that only
 * surfaces months later, inside curriculum work.
 */
class LearningPhaseSeeder extends Seeder
{
    /** @var array<string, array{name: string, sequence: int, grades: string[]}> */
    private const PHASES = [
        'FOUNDATION' => ['name' => 'Foundation', 'sequence' => 1, 'grades' => ['Kindergarten 1', 'Kindergarten 2']],
        'A' => ['name' => 'Phase A', 'sequence' => 2, 'grades' => ['Year 1', 'Year 2']],
        'B' => ['name' => 'Phase B', 'sequence' => 3, 'grades' => ['Year 3', 'Year 4']],
        'C' => ['name' => 'Phase C', 'sequence' => 4, 'grades' => ['Year 5', 'Year 6']],
        'D' => ['name' => 'Phase D', 'sequence' => 5, 'grades' => ['Year 7', 'Year 8', 'Year 9']],
        'E' => ['name' => 'Phase E', 'sequence' => 6, 'grades' => ['Year 10']],
        'F' => ['name' => 'Phase F', 'sequence' => 7, 'grades' => ['Year 11', 'Year 12']],
    ];

    public function run(): void
    {
        foreach (self::PHASES as $code => $definition) {
            $phase = LearningPhase::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'sequence' => $definition['sequence'],
                    'status' => 'active',
                ],
            );

            foreach ($definition['grades'] as $gradeName) {
                $grade = Grade::where('name', $gradeName)->first();

                if (! $grade) {
                    throw new RuntimeException(
                        "Learning phase {$code} expects the grade \"{$gradeName}\", which does not exist. ".
                        'Seed grades first, or correct the phase definition -- do not let a phase seed half-mapped.'
                    );
                }

                // Written through the model, not attach(), so the mapping is
                // audited like every other academic reference change.
                LearningPhaseGrade::firstOrCreate([
                    'learning_phase_id' => $phase->id,
                    'grade_id' => $grade->id,
                ]);
            }
        }
    }
}
