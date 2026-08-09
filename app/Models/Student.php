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
    'photo_path', 'enrollment_date', 'status', 'user_id',
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

    public function currentClass(): ?SchoolClass
    {
        $currentYear = AcademicYear::current();

        if (! $currentYear) {
            return null;
        }

        return $this->classes()
            ->where('academic_year_id', $currentYear->id)
            ->wherePivot('status', 'active')
            ->first();
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

    /**
     * Restrict a query to students enrolled in a class the given staff member teaches.
     */
    public function scopeTaughtBy(Builder $query, int $staffId): Builder
    {
        return $query->whereHas('classes.teachers', fn ($q) => $q->where('staff_id', $staffId));
    }
}
