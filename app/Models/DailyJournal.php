<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Jurnal Harian Guru / Daily Teaching Journal -- what actually happened.
 *
 * The assignment anchor is immutable in every state: moving a journal between
 * teaching assignments through an edit would rewrite whose history it was.
 *
 * Two staff facts, and they mean different things. class_subject.staff_id is
 * who was responsible; conducted_by_staff_id is who actually taught. A
 * substitute lesson names the substitute here and leaves the assignment alone.
 */
#[Fillable([
    'class_subject_id', 'class_id', 'teaching_group_id', 'subject_id', 'curriculum_scope_id',
    'academic_year_id', 'academic_period_id', 'journal_date', 'conducted_by_staff_id',
    'teaching_module_id', 'semester_programme_item_id', 'meeting_number',
    'topic', 'actual_activity', 'reflection', 'follow_up', 'actual_lesson_periods', 'status',
])]
class DailyJournal extends Model
{
    use Auditable;

    /** Immutable in EVERY state, finalized or not. */
    private const IDENTITY = [
        'class_subject_id', 'class_id', 'teaching_group_id', 'subject_id', 'academic_year_id',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $journal) {
            foreach (self::IDENTITY as $column) {
                if ($journal->isDirty($column)) {
                    throw new LogicException(
                        "A journal's teaching assignment is fixed at creation ({$column}). ".
                        'A session cannot be moved to a different assignment.'
                    );
                }
            }
        });

        static::deleting(function (self $journal) {
            if ($journal->status !== 'draft') {
                throw new LogicException(
                    'A finalized journal is a record of what happened and is never deleted. '.
                    'A manager may correct it instead.'
                );
            }
        });
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teachingGroup(): BelongsTo
    {
        return $this->belongsTo(TeachingGroup::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function curriculumScope(): BelongsTo
    {
        return $this->belongsTo(CurriculumScope::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function conductedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'conducted_by_staff_id');
    }

    public function teachingModule(): BelongsTo
    {
        return $this->belongsTo(TeachingModule::class);
    }

    public function semesterProgrammeItem(): BelongsTo
    {
        return $this->belongsTo(SemesterProgrammeItem::class);
    }

    public function objectiveLinks(): HasMany
    {
        return $this->hasMany(DailyJournalLearningObjective::class);
    }

    public function assessmentLinks(): HasMany
    {
        return $this->hasMany(DailyJournalAssessment::class);
    }

    public function objectives()
    {
        return LearningObjective::whereIn('id', $this->objectiveLinks()->pluck('learning_objective_id'))
            ->orderBy('reference_order')->get();
    }

    public function isClassBacked(): bool
    {
        return $this->class_id !== null;
    }

    public function rosterName(): string
    {
        return $this->isClassBacked()
            ? ($this->schoolClass?->name ?? 'Unknown class')
            : ($this->teachingGroup?->name ?? 'Unknown group');
    }

    public function rosterLabel(): string
    {
        return $this->isClassBacked() ? 'Class' : 'Teaching Group';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinalized(): bool
    {
        return $this->status === 'finalized';
    }

    /** True when the person who taught was not the assigned teacher. */
    public function wasSubstituted(): bool
    {
        return $this->conducted_by_staff_id !== $this->classSubject?->staff_id;
    }

    public function scopeFinalized(Builder $query): Builder
    {
        return $query->where('status', 'finalized');
    }

    /** Chronological. meeting_number is advisory and never orders anything. */
    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('journal_date')->orderBy('id');
    }
}
