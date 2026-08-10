<?php

namespace App\Services;

use App\Models\EnglishLevel;
use App\Models\Student;
use App\Models\StudentEnglishLevelPlacement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A student's assessed English proficiency, recorded as close-and-open
 * history.
 *
 * Note what this class deliberately does NOT do: it never touches teaching
 * group membership. Proficiency and the group a student actually sits in are
 * separate facts, and a re-assessment is information for a human deciding
 * whether to move the student -- not an instruction to move them.
 */
class EnglishPlacementService
{
    public function __construct(private StudentGradeResolver $grades) {}

    public function current(Student $student): ?StudentEnglishLevelPlacement
    {
        return $student->englishPlacements()->open()->with('englishLevel.programme')->first();
    }

    /**
     * Record a new assessed level, closing any open placement the day before
     * the new one starts. The partial unique index is the backstop; this is
     * the path that keeps the history sensible.
     */
    public function place(
        Student $student,
        EnglishLevel $level,
        Carbon $startedOn,
        ?Carbon $assessedOn = null,
        ?string $reason = null,
        ?string $notes = null,
    ): StudentEnglishLevelPlacement {
        $this->assertEligible($student, $level, $startedOn);

        if ($assessedOn && $assessedOn->gt($startedOn)) {
            $this->fail('assessed_on', 'The assessment date cannot be after the date the placement starts.');
        }

        return DB::transaction(function () use ($student, $level, $startedOn, $assessedOn, $reason, $notes) {
            Student::whereKey($student->id)->lockForUpdate()->first();

            $open = StudentEnglishLevelPlacement::where('student_id', $student->id)
                ->whereNull('ended_on')
                ->first();

            if ($open) {
                if ($startedOn->lte($open->started_on)) {
                    $this->fail('started_on', "The new placement must start after the current one began ({$open->started_on->toDateString()}).");
                }

                $open->update(['ended_on' => $startedOn->copy()->subDay()]);
            }

            return StudentEnglishLevelPlacement::create([
                'student_id' => $student->id,
                'english_level_id' => $level->id,
                'started_on' => $startedOn,
                'assessed_on' => $assessedOn,
                'placement_reason' => $reason,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Close the open placement without opening a replacement -- a student who
     * has left the programme, not one who has moved level.
     */
    public function close(StudentEnglishLevelPlacement $placement, Carbon $endedOn): StudentEnglishLevelPlacement
    {
        if (! $placement->isOpen()) {
            $this->fail('ended_on', 'That placement has already been closed.');
        }

        if ($endedOn->lt($placement->started_on)) {
            $this->fail('ended_on', 'The end date cannot be before the start date.');
        }

        $placement->update(['ended_on' => $endedOn]);

        return $placement;
    }

    /**
     * Levels a student may legitimately be placed at: those in the programme
     * covering their grade. Empty when the grade maps to no programme.
     */
    public function eligibleLevels(Student $student, ?Carbon $on = null): \Illuminate\Support\Collection
    {
        $grade = $this->grades->gradeOn($student, $on ?? Carbon::today());
        $programme = $grade?->englishProgramme();

        if (! $programme) {
            return collect();
        }

        return $programme->levels()->where('status', 'active')->get();
    }

    public function ineligibilityReason(Student $student, EnglishLevel $level, Carbon $on): ?string
    {
        $grade = $this->grades->gradeOn($student, $on, $reason);

        if (! $grade) {
            return $reason === StudentGradeResolver::AMBIGUOUS
                ? "{$student->fullName()} is in classes from more than one grade, so their grade cannot be determined."
                : "{$student->fullName()} has no active class for that date, so their grade cannot be determined.";
        }

        $studentProgramme = $grade->englishProgramme();

        if (! $studentProgramme) {
            return "{$grade->name} is not covered by any English programme.";
        }

        $levelProgramme = $level->programme;

        if (! $levelProgramme || $levelProgramme->id !== $studentProgramme->id) {
            return "{$level->name} belongs to the {$levelProgramme?->name}, but {$grade->name} follows the {$studentProgramme->name}.";
        }

        return null;
    }

    private function assertEligible(Student $student, EnglishLevel $level, Carbon $on): void
    {
        if ($reason = $this->ineligibilityReason($student, $level, $on)) {
            $this->fail('english_level_id', $reason);
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
