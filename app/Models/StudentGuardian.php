<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['relationship_type', 'is_primary_contact', 'can_pickup', 'notes'])]
class StudentGuardian extends Pivot
{
    protected $table = 'student_guardian';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'is_primary_contact' => 'boolean',
            'can_pickup' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }
}
