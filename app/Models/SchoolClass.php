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
            ->withPivot(['enrolled_at', 'status'])
            ->withTimestamps();
    }

    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'class_teacher', 'class_id', 'staff_id')
            ->using(ClassTeacher::class)
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function homeroomTeacher(): ?Staff
    {
        return $this->teachers()->wherePivot('role', 'homeroom')->first();
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
     * Restrict a query to classes the given staff member teaches.
     */
    public function scopeTaughtBy(Builder $query, int $staffId): Builder
    {
        return $query->whereHas('teachers', fn ($q) => $q->where('staff_id', $staffId));
    }
}
