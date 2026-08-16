<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['name', 'start_date', 'end_date', 'is_current'])]
class AcademicYear extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    /**
     * Reporting periods, in their configured order. The count and names are
     * data, never assumed by application code.
     */
    public function periods(): HasMany
    {
        return $this->hasMany(AcademicPeriod::class)->orderBy('sequence');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function teachingGroups(): HasMany
    {
        return $this->hasMany(TeachingGroup::class);
    }

    /**
     * The single current Academic Year, or null if none is set -- callers
     * that already tolerate "no current year" (UI defaults, optional
     * scoping) keep doing so. Deliberately not `->first()`: since
     * `academic_years_current_unique` (Foundation F1) makes more than one
     * is_current=true row a database-level impossibility through any normal
     * write path, silently picking one here would only ever mask the one
     * situation -- a constraint bypassed some other way -- that a caller
     * genuinely needs to know about rather than have guessed away.
     *
     * No `currentOrFail()` companion: every existing caller already
     * tolerates a null current year (see Foundation F1 caller review), so a
     * throwing variant would have no caller and is not added speculatively.
     */
    public static function current(): ?self
    {
        $current = static::where('is_current', true)->get();

        return match ($current->count()) {
            0 => null,
            1 => $current->first(),
            default => throw new LogicException(
                'More than one AcademicYear is flagged is_current ('
                .$current->pluck('id')->join(', ')
                .'). This should be impossible once academic_years_current_unique is'
                .' in place -- resolve the conflicting rows directly before calling'
                .' AcademicYear::current() again.'
            ),
        };
    }
}
