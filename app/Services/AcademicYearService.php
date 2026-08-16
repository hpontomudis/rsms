<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

/**
 * The one canonical write path for `academic_years.is_current`. Foundation
 * F1: `academic_years_current_unique` enforces at most one current row at
 * the database layer; this service enforces exactly one at the application
 * layer, so no caller that goes through it ever observes a window with zero
 * current years -- the old seeder's wipe-then-set two-step (which briefly
 * left every row not-current) no longer exists anywhere in the codebase.
 *
 * No independent checkbox-style write of is_current exists outside this
 * service -- AcademicYearSeeder calls it too (see its docblock).
 */
class AcademicYearService
{
    /**
     * Make $year the current Academic Year, closing whichever year (if any)
     * was current before. Idempotent: calling this with the year that is
     * already current changes nothing and writes no audit entry.
     */
    public function setCurrent(AcademicYear $year): AcademicYear
    {
        return DB::transaction(function () use ($year) {
            // Locks both the row(s) currently flagged and the target row,
            // so two concurrent switches serialize instead of racing.
            // No-op on SQLite, which serializes writers anyway (same
            // reasoning as TeachingGroupMembershipService::add()).
            AcademicYear::where('is_current', true)
                ->orWhere($year->getKeyName(), $year->id)
                ->lockForUpdate()
                ->get();

            // Per-model update, not a bulk query update -- bulk updates
            // bypass Eloquent model events, which would silently skip the
            // Auditable trail for the row being closed.
            AcademicYear::where('is_current', true)
                ->whereKeyNot($year->id)
                ->get()
                ->each(fn (AcademicYear $previous) => $previous->update(['is_current' => false]));

            $fresh = AcademicYear::findOrFail($year->id);

            if (! $fresh->is_current) {
                $fresh->update(['is_current' => true]);
            }

            return $fresh->refresh();
        });
    }
}
