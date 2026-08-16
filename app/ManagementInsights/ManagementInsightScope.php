<?php

namespace App\ManagementInsights;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\User;

/**
 * Explicit inputs to every ManagementInsightProvider -- never a raw request
 * array, never an implicit "current" resolution. AcademicYear::current()
 * MUST NOT be called inside a provider (its underlying `is_current` boolean
 * has no DB-level uniqueness guarantee and no ambiguity-safe resolver, per
 * PROJECT_STATUS.md's Foundation Technical Debt). The Livewire dashboard
 * resolves the year/period once at the UI boundary and passes it down here.
 *
 * `academicPeriod` is nullable because some providers are period-scoped
 * (Semester Programme completeness, Academic Record publication) and some
 * aren't (Draft Modules across the whole year, Staff without a category).
 * A period-scoped provider that receives null should return an
 * `unavailable` insight rather than silently defaulting to "any period."
 */
final readonly class ManagementInsightScope
{
    public function __construct(
        public AcademicYear $academicYear,
        public ?AcademicPeriod $academicPeriod,
        public User $actor,
    ) {}
}
