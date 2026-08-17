<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class/section within a grade for a specific academic year, e.g. "Year 5A".
 * Model name avoids the reserved word `Class`; the table remains `classes`.
 */
#[Fillable(['name', 'grade_id', 'academic_year_id', 'capacity'])]
class SchoolClass extends Model
{
    protected $table = 'classes';

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classStudents(): HasMany
    {
        return $this->hasMany(ClassStudent::class, 'class_id');
    }

    public function classTeachers(): HasMany
    {
        return $this->hasMany(ClassTeacher::class, 'class_id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'class_student', 'class_id', 'student_id')
            ->using(ClassStudent::class)
            ->withPivot(['enrolled_at', 'status', 'ended_on'])
            ->withTimestamps();
    }

    /**
     * The roster effective on $date -- students whose class_student row's
     * half-open [enrolled_at, ended_on) window covers $date (Foundation
     * F3). The one reusable "who was here on date Y" helper; `ClassSubject
     * ::rosterOn()` (class-backed) and Attendance both go through this
     * rather than each re-deriving the boundary rule.
     */
    public function studentsOn(\Illuminate\Support\Carbon $date): \Illuminate\Support\Collection
    {
        $on = $date->toDateString();

        // whereDate(), not where(): a `date`-cast column can be stored with
        // a spurious `00:00:00` time suffix on SQLite, which makes plain
        // string comparison against a bare 'Y-m-d' value lexicographically
        // wrong on the boundary day itself (`'2026-08-15 00:00:00' >
        // '2026-08-15'` is TRUE as a string compare). whereDate() strips
        // the time component on every driver before comparing.
        return $this->students()
            ->whereDate('class_student.enrolled_at', '<=', $on)
            ->where(fn ($q) => $q->whereNull('class_student.ended_on')->orWhereDate('class_student.ended_on', '>', $on))
            ->get();
    }

    /**
     * Every Student whose enrollment window overlaps [$start, $end] at all
     * -- used by date-RANGE reporting (Attendance\Report), where a Student
     * who transferred out mid-range must still appear for the days they
     * were genuinely enrolled, rather than vanishing because they are no
     * longer on today's roster.
     */
    public function studentsEnrolledBetween(\Illuminate\Support\Carbon $start, \Illuminate\Support\Carbon $end): \Illuminate\Support\Collection
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        return $this->students()
            ->whereDate('class_student.enrolled_at', '<=', $endDate)
            ->where(fn ($q) => $q->whereNull('class_student.ended_on')->orWhereDate('class_student.ended_on', '>', $startDate))
            ->get();
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'class_teacher', 'class_id', 'staff_id')
            ->using(ClassTeacher::class)
            ->withPivot(['role', 'started_on', 'ended_on'])
            ->withTimestamps();
    }

    /**
     * The current homeroom teacher, or null. Deliberately CURRENT only, not
     * point-in-time: no existing consumer needs a historical "who was
     * homeroom teacher on date X" answer (`AcademicRecordService`'s
     * homeroom resolution is documented as current-only too, matching
     * `resolveClass()`'s same choice), so a `homeroomTeacherOn(date)`
     * historical resolver was deliberately not built -- Foundation F2.
     *
     * More than one open homeroom row is a database-level impossibility
     * once `class_teacher_homeroom_open_unique` exists, but this still
     * fails loud rather than guessing, the same defense-in-depth already
     * used by `AcademicYear::current()`.
     */
    public function homeroomTeacher(): ?Staff
    {
        $current = $this->teachers()->wherePivot('role', 'homeroom')->wherePivotNull('ended_on')->get();

        return match ($current->count()) {
            0 => null,
            1 => $current->first(),
            default => throw new \LogicException(
                "Class #{$this->id} has more than one open homeroom row. This should be impossible once ".
                'class_teacher_homeroom_open_unique is in place -- resolve the conflicting rows directly.'
            ),
        };
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(Attendance::class, 'class_id');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'class_id');
    }

    /**
     * Restrict a query to classes the given staff member currently teaches
     * (any open class_teacher row -- homeroom, assistant, or a legacy-only
     * open subject_teacher row). A closed/historical row no longer counts,
     * matching Foundation F2's "current authorization ignores closed rows"
     * rule -- this scope feeds UI filtering (My Classes, attendance class
     * picker), so a stale row here would offer a class the corresponding
     * policy check (`AttendancePolicy::hasClassAccess()`) then correctly
     * refuses.
     */
    public function scopeTaughtBy(Builder $query, int $staffId): Builder
    {
        return $query->whereHas('teachers', fn ($q) => $q->where('staff_id', $staffId)->whereNull('class_teacher.ended_on'));
    }
}
