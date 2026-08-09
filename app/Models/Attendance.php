<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['class_id', 'academic_year_id', 'date', 'taken_by', 'notes'])]
class Attendance extends Model
{
    use Auditable;

    protected $table = 'attendance';

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'taken_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isEditableToday(): bool
    {
        return $this->date->isToday();
    }
}
