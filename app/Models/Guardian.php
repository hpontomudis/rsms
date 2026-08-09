<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['first_name', 'last_name', 'phone', 'email', 'address', 'occupation', 'user_id'])]
class Guardian extends Model
{
    use Auditable, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_guardian')
            ->using(StudentGuardian::class)
            ->withPivot(['id', 'relationship_type', 'is_primary_contact', 'can_pickup', 'notes'])
            ->withTimestamps();
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
