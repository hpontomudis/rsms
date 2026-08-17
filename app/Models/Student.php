<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'student_number', 'first_name', 'last_name', 'date_of_birth', 'gender',
    'photo_path', 'nik', 'nisn', 'enrollment_date', 'status', 'user_id',
])]
class Student extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'enrollment_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardian')
            ->using(StudentGuardian::class)
            ->withPivot(['id', 'relationship_type', 'is_primary_contact', 'can_pickup', 'notes'])
            ->withTimestamps();
    }

    public function primaryGuardian(): ?Guardian
    {
        return $this->guardians()->wherePivot('is_primary_contact', true)->first();
    }

    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_student', 'student_id', 'class_id')
            ->using(ClassStudent::class)
            ->withPivot(['enrolled_at', 'status'])
            ->withTimestamps();
    }

    /**
     * The current administrative class, or null. Resolves the single OPEN
     * `class_student` row directly (Foundation F3) -- no more dependency on
     * `AcademicYear::current()`, since an open enrollment already answers
     * "current" on its own. Fails loud rather than guessing if corruption
     * somehow produces more than one open row (structurally impossible once
     * `class_student_current_enrollment_unique` exists, but not trusted
     * blindly -- the same defense-in-depth as `AcademicYear::current()` and
     * `SchoolClass::homeroomTeacher()`).
     */
    public function currentClass(): ?SchoolClass
    {
        $open = $this->classStudents()->open()->with('schoolClass')->get();

        return match ($open->count()) {
            0 => null,
            1 => $open->first()->schoolClass,
            default => throw new \LogicException(
                "Student #{$this->id} has more than one open class_student row. This should be impossible once ".
                'class_student_current_enrollment_unique is in place -- resolve the conflicting rows directly.'
            ),
        };
    }

    /**
     * The administrative class effective on $date -- the minimum historical
     * chronology helper needed by downstream consumers (report-card and
     * roster point-in-time resolution). Same fail-loud discipline as
     * currentClass().
     */
    public function classOn(\Illuminate\Support\Carbon $date): ?SchoolClass
    {
        $rows = $this->classStudents()->effectiveOn($date)->with('schoolClass')->get();

        return match ($rows->count()) {
            0 => null,
            1 => $rows->first()->schoolClass,
            default => throw new \LogicException(
                "Student #{$this->id} has more than one class_student row effective on {$date->toDateString()}. ".
                'This should be impossible once overlap validation is enforced -- resolve the conflicting rows directly.'
            ),
        };
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function outstandingBalance(): float
    {
        return (float) $this->invoices()
            ->where('status', '!=', 'void')
            ->with('items', 'discounts', 'payments')
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->balance());
    }

    public function assessmentResults(): HasMany
    {
        return $this->hasMany(AssessmentResult::class);
    }

    /**
     * Teaching-group memberships, open and closed. No belongsToMany shortcut:
     * writes must go through the model so they are audited.
     */
    public function teachingGroupMemberships(): HasMany
    {
        return $this->hasMany(TeachingGroupStudent::class);
    }

    /**
     * Assessed English proficiency over time, newest first.
     */
    public function englishPlacements(): HasMany
    {
        return $this->hasMany(StudentEnglishLevelPlacement::class)->orderByDesc('started_on');
    }

    /**
     * Restrict a query to students enrolled in a class the given staff
     * member CURRENTLY teaches -- a closed class_teacher row no longer
     * counts (Foundation F2).
     */
    public function scopeTaughtBy(Builder $query, int $staffId): Builder
    {
        return $query->whereHas('classes.teachers', fn ($q) => $q->where('staff_id', $staffId)->whereNull('class_teacher.ended_on'));
    }
}
