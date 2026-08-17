<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Resolves a student's administrative grade from the EXISTING class
 * membership structure. There is no second student-grade field anywhere, and
 * this class exists so there is exactly one place that knows how to answer
 * the question.
 *
 * The path is: student -> class_student (open row) -> classes -> grades.
 *
 * FOUNDATION F3: `class_student_current_enrollment_unique` now makes it a
 * database-level impossibility for a Student to hold two OPEN rows at once,
 * so `gradeForYear()`'s "active classes span more than one grade" ambiguity
 * case is structurally unreachable through any normal write path -- a
 * mid-year transfer closes the old row and opens the new one, so exactly
 * one row is ever open, whichever grade it belongs to. The AMBIGUOUS check
 * stays as defense-in-depth (the same "DB enforces at most one, resolver
 * still refuses to guess" discipline as `AcademicYear::current()` and
 * `SchoolClass::homeroomTeacher()`), not because it is expected to fire.
 *
 * A legitimate same-grade mid-year transfer (Year 5A -> Year 5B, both Grade
 * "Year 5") resolves correctly and unambiguously by construction: only the
 * new, open row is ever considered, and it points at the same grade the
 * closed predecessor did.
 */
class StudentGradeResolver
{
    public const NO_CLASS = 'no_active_class';

    public const AMBIGUOUS = 'ambiguous_grade';

    public const NO_YEAR = 'no_academic_year';

    public const AMBIGUOUS_YEAR = 'ambiguous_academic_year';

    /**
     * The student's CURRENT grade, scoped to one academic year, or null if
     * it cannot be determined unambiguously. Uses the single OPEN
     * `class_student` row only -- never point-in-time; see gradeOn() for a
     * genuinely historical (possibly backdated) resolution instead.
     * $reason receives NO_CLASS or AMBIGUOUS.
     */
    public function gradeForYear(Student $student, int $academicYearId, ?string &$reason = null): ?Grade
    {
        $grades = Grade::whereHas('classes', function ($query) use ($student, $academicYearId) {
            $query->where('academic_year_id', $academicYearId)
                ->whereHas('classStudents', fn ($q) => $q->where('student_id', $student->id)
                    ->where('status', 'active')
                    ->whereNull('ended_on'));
        })->get();

        if ($grades->isEmpty()) {
            $reason = self::NO_CLASS;

            return null;
        }

        if ($grades->count() > 1) {
            $reason = self::AMBIGUOUS;

            return null;
        }

        $reason = null;

        return $grades->first();
    }

    /**
     * The academic year a date falls in, used when the caller has a date but
     * no year (a placement carries no academic_year_id -- the student's grade
     * is whatever it was when the placement started).
     *
     * academic_years.start_date and end_date are both NOT NULL, so the range
     * test is safe. There is deliberately NO fallback to the year flagged
     * current: substituting today's year for an unmatched historical date
     * would validate a 2024 placement against 2026's grade and say nothing.
     * Zero matches and multiple matches are both reported, never guessed
     * through -- nothing in the schema stops two academic years overlapping.
     */
    public function yearForDate(Carbon $date, ?string &$reason = null): ?AcademicYear
    {
        $matches = AcademicYear::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->get();

        if ($matches->isEmpty()) {
            $reason = self::NO_YEAR;

            return null;
        }

        if ($matches->count() > 1) {
            $reason = self::AMBIGUOUS_YEAR;

            return null;
        }

        $reason = null;

        return $matches->first();
    }

    /**
     * The student's grade AS AT a date -- genuinely point-in-time, not the
     * student's current grade. Matters because a caller here (e.g. English
     * placement backdating) can legitimately ask about a past date after
     * the student has since transferred classes; resolving through today's
     * open enrollment would silently answer the wrong question. Uses
     * Student::classOn($date), which reads the class_student row whose
     * half-open [enrolled_at, ended_on) window covers $date, never the
     * current-only `status = active` signal gradeForYear() uses.
     */
    public function gradeOn(Student $student, Carbon $date, ?string &$reason = null): ?Grade
    {
        $year = $this->yearForDate($date, $reason);

        if (! $year) {
            return null;
        }

        $class = $student->classOn($date);

        if (! $class || $class->academic_year_id !== $year->id) {
            $reason = self::NO_CLASS;

            return null;
        }

        $reason = null;

        return $class->grade;
    }

    /**
     * A human-readable explanation for any of the four failure reasons, so
     * callers do not each invent their own wording.
     */
    public function explain(?string $reason, Student $student, Carbon $date): string
    {
        return match ($reason) {
            self::NO_YEAR => 'The date '.$date->toDateString().' does not fall within a configured Academic Year.',
            self::AMBIGUOUS_YEAR => 'The date '.$date->toDateString().' falls within more than one configured Academic Year, so the school year cannot be determined.',
            self::AMBIGUOUS => "{$student->fullName()} has active classes in more than one grade for that Academic Year, so their grade cannot be determined.",
            default => "{$student->fullName()} has no active class for that date, so their grade cannot be determined.",
        };
    }
}
