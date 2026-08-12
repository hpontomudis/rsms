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
 * An officially issued report card for one student and one academic period.
 *
 * A LIVE report card is a view of current data. THIS is a record of what was
 * issued. The two are allowed to disagree, and when they do, the disagreement
 * is the point: a family holds a printed copy of these numbers.
 *
 * Immutable once published. Correcting an issued result does not edit this row
 * -- it publishes a replacement, which supersedes this one, and this one
 * survives forever.
 */
#[Fillable([
    'student_id', 'academic_year_id', 'academic_period_id', 'class_id',
    'student_name_snapshot', 'student_number_snapshot', 'class_name_snapshot',
    'grade_name_snapshot', 'period_name_snapshot', 'academic_year_name_snapshot',
    'school_name_snapshot', 'school_line2_snapshot', 'school_address_snapshot',
    'principal_name_snapshot', 'principal_title_snapshot', 'homeroom_teacher_name_snapshot',
    'overall_average', 'homeroom_comment', 'notes', 'status',
    'published_at', 'published_by_user_id', 'supersedes_id',
])]
class AcademicRecord extends Model
{
    use Auditable;

    /** What this record is FOR. Fixed from creation, draft or not. */
    private const IDENTITY = ['student_id', 'academic_year_id', 'academic_period_id'];

    /**
     * Everything frozen at publication. Once issued, none of it may move --
     * not the scores, not the labels, not the signatories.
     */
    private const FROZEN = [
        'student_id', 'academic_year_id', 'academic_period_id', 'class_id',
        'student_name_snapshot', 'student_number_snapshot', 'class_name_snapshot',
        'grade_name_snapshot', 'period_name_snapshot', 'academic_year_name_snapshot',
        'school_name_snapshot', 'school_line2_snapshot', 'school_address_snapshot',
        'principal_name_snapshot', 'principal_title_snapshot', 'homeroom_teacher_name_snapshot',
        'overall_average', 'homeroom_comment', 'notes', 'published_at',
        'published_by_user_id', 'supersedes_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $record) {
            foreach (self::IDENTITY as $column) {
                if ($record->isDirty($column)) {
                    throw new LogicException(
                        "An academic record's student and period are fixed at creation ({$column})."
                    );
                }
            }

            // Published and superseded records are historical. The ONLY change
            // either accepts is published -> superseded, when a correction
            // replaces it.
            if (in_array($record->getOriginal('status'), ['published', 'superseded'], true)) {
                foreach (self::FROZEN as $column) {
                    if ($record->isDirty($column)) {
                        throw new LogicException(
                            "An issued academic record is immutable ({$column}). ".
                            'Publish a correction instead; it will supersede this one.'
                        );
                    }
                }

                if ($record->getOriginal('status') === 'superseded' && $record->isDirty('status')) {
                    throw new LogicException('A superseded academic record cannot be reopened.');
                }

                if ($record->isDirty('status') && $record->status !== 'superseded') {
                    throw new LogicException('An issued academic record cannot be unpublished.');
                }
            }
        });

        static::deleting(function (self $record) {
            if ($record->status !== 'draft') {
                throw new LogicException(
                    'An issued academic record is never deleted. A correction supersedes it instead.'
                );
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /** The record this one replaced. */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** The record that replaced this one, if any. */
    public function supersededBy()
    {
        return self::where('supersedes_id', $this->id)->first();
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(AcademicRecordSubject::class)->orderBy('position');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isSuperseded(): bool
    {
        return $this->status === 'superseded';
    }

    /** Published or superseded: issued, historical, immutable. */
    public function isIssued(): bool
    {
        return ! $this->isDraft();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }
}
