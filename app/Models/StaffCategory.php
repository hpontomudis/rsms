<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What kind of staff member this is -- Teacher, Driver, Security, and so on --
 * for the one purpose that needs it: which Performance Framework applies.
 *
 * `code` is the identity a framework references and should be treated as
 * stable once anything points at it; `name` is presentation and may be
 * corrected freely.
 */
#[Fillable(['code', 'name', 'description'])]
class StaffCategory extends Model
{
    use Auditable;

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function frameworks(): HasMany
    {
        return $this->hasMany(PerformanceFramework::class);
    }

    public function isReferenced(): bool
    {
        return $this->staff()->exists() || $this->frameworks()->exists();
    }
}
