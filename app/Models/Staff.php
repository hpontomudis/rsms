<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'staff_number', 'first_name', 'last_name', 'position_id', 'staff_category_id',
    'phone', 'email', 'nik', 'hire_date', 'status', 'user_id',
])]
class Staff extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'staff';

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function staffCategory(): BelongsTo
    {
        return $this->belongsTo(StaffCategory::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classTeachers(): HasMany
    {
        return $this->hasMany(ClassTeacher::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_teacher', 'staff_id', 'class_id')
            ->using(ClassTeacher::class)
            ->withPivot(['role', 'started_on', 'ended_on'])
            ->withTimestamps();
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function attendanceTaken(): HasMany
    {
        return $this->hasMany(Attendance::class, 'taken_by');
    }

    public function approvedDiscounts(): HasMany
    {
        return $this->hasMany(Discount::class, 'approved_by');
    }

    public function receivedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    public function performanceEvaluations(): HasMany
    {
        return $this->hasMany(PerformanceEvaluation::class);
    }
}
