<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * NOTE: the `term` column is DEPRECATED. `academic_period_id` is the single
 * canonical source of an assessment's reporting period. `term` is retained
 * only for rollback safety during this phase and is deliberately absent from
 * $fillable so no code path can write to it. It will be dropped in a later
 * cleanup migration.
 */
#[Fillable(['class_subject_id', 'academic_period_id', 'name', 'max_score', 'assessment_date'])]
class Assessment extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'max_score' => 'decimal:2',
            'assessment_date' => 'date',
        ];
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    /**
     * Everyone who belongs on this assessment's score sheet.
     *
     * Two sets, unioned:
     *   1. the roster as at assessment_date -- who was actually being taught
     *      when this assessment happened;
     *   2. anyone who already has a result on it.
     *
     * (2) exists because a score is historical academic evidence. A student
     * who scored 85 in Green A and later moved to Blue A must still appear on
     * the Green A assessment with their 85 -- the mark does not stop being
     * true because the student moved. Nothing is snapshotted to achieve this:
     * assessment_results already records exactly who was scored.
     */
    public function scoreSheetStudents(): Collection
    {
        $assignment = $this->classSubject;

        $eligible = $assignment->rosterOn($this->assessment_date);
        $alreadyScored = Student::whereIn('id', $this->results()->pluck('student_id'))->get();

        return $eligible->concat($alreadyScored)
            ->unique('id')
            ->sortBy(fn (Student $student) => $student->fullName())
            ->values();
    }

    /**
     * Which students may legitimately receive a score here -- the same union.
     * Used as an allowlist on write, so a tampered form payload cannot invent
     * a student who was never on this roster. Sharing a grade with the group
     * is explicitly NOT sufficient.
     */
    public function scorableStudentIds(): Collection
    {
        return $this->scoreSheetStudents()->pluck('id');
    }
}
