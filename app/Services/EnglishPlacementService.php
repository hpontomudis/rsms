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

            // Checked BEFORE the open row is closed, and ignoring it, because
            // that row is about to end the day before this one starts. Any
            // OTHER row still covering this date is a genuine clash -- most
            // often a backdated correction landing inside earlier history.
            $this->assertNoOverlap($student, $startedOn, null, $open?->id);

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

    /**
     * A student's assessed level is one fact with one validity period, so two
     * placements may never be in force at the same time. The partial unique
     * index only stops two OPEN rows; overlapping CLOSED history slips past
     * it, and cannot be expressed as a portable constraint without a range
     * type or an exclusion constraint (PostgreSQL-only, and SQLite runs the
     * test suite). Hence a service check, inside the same lock as the write.
     *
     * Public so the rule itself can be tested directly, including the
     * closed-versus-closed case that no UI path can produce.
     */
    public function overlappingPlacement(
        Student $student,
        Carbon $startedOn,
        ?Carbon $endedOn = null,
        ?int $ignoreId = null,
    ): ?StudentEnglishLevelPlacement {
        $query = StudentEnglishLevelPlacement::where('student_id', $student->id)
            // existing.ended_on >= new.started_on (an open row never ends)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $startedOn));

        // existing.started_on <= new.ended_on; an open new row has no ceiling
        if ($endedOn) {
            $query->where('started_on', '<=', $endedOn);
        }

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->with('englishLevel')->first();
    }

    private function assertNoOverlap(Student $student, Carbon $startedOn, ?Carbon $endedOn, ?int $ignoreId = null): void
    {
        $clash = $this->overlappingPlacement($student, $startedOn, $endedOn, $ignoreId);

        if (! $clash) {
            return;
        }

        $until = $clash->ended_on?->toDateString() ?? 'now';

        $this->fail('started_on', "{$student->fullName()} is already recorded as {$clash->englishLevel->name} from {$clash->started_on->toDateString()} to {$until}. Proficiency periods cannot overlap.");
    }

    public function ineligibilityReason(Student $student, EnglishLevel $level, Carbon $on): ?string
    {
        $grade = $this->grades->gradeOn($student, $on, $reason);

        if (! $grade) {
            return $this->grades->explain($reason, $student, $on);
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
