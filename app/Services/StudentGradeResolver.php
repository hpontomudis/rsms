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
 * The path is: student -> class_student (status = active) -> classes
 * (filtered to one academic year) -> grades.
 *
 * KNOWN AMBIGUITY IN THE PHASE 1 SCHEMA (documented, not redesigned here):
 * class_student is flat -- unique(class_id, student_id) with a status enum,
 * no effective dating -- and nothing at the database level stops a student
 * from holding two `active` rows for two different classes in the same
 * academic year. Student::currentClass() resolves that by taking first(),
 * which is silent and order-dependent.
 *
 * Eligibility checks must not be silent, so this resolver refuses to guess:
 * if the active classes for a year point at more than one distinct grade, it
 * returns null and the caller reports the data problem instead of picking a
 * grade arbitrarily. Several active classes that all sit in the SAME grade
 * are unambiguous and resolve normally.
 */
class StudentGradeResolver
{
    public const NO_CLASS = 'no_active_class';

    public const AMBIGUOUS = 'ambiguous_grade';

    /**
     * The student's grade within one academic year, or null if it cannot be
     * determined unambiguously. $reason receives NO_CLASS or AMBIGUOUS.
     */
    public function gradeForYear(Student $student, int $academicYearId, ?string &$reason = null): ?Grade
    {
        $grades = Grade::whereHas('classes', function ($query) use ($student, $academicYearId) {
            $query->where('academic_year_id', $academicYearId)
                ->whereHas('classStudents', fn ($q) => $q->where('student_id', $student->id)->where('status', 'active'));
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
     * test is safe. If no year covers the date -- a gap between years, or a
     * year not yet created -- this falls back to the year flagged current,
     * which is what an administrator entering data today means.
     */
    public function yearForDate(Carbon $date): ?AcademicYear
    {
        $matches = AcademicYear::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        // Zero matches, or overlapping years someone configured by hand:
        // neither is safe to guess through, so defer to the current year.
        return AcademicYear::current();
    }

    /**
     * The student's grade as at a date, resolved through that date's year.
     */
    public function gradeOn(Student $student, Carbon $date, ?string &$reason = null): ?Grade
    {
        $year = $this->yearForDate($date);

        if (! $year) {
            $reason = self::NO_CLASS;

            return null;
        }

        return $this->gradeForYear($student, $year->id, $reason);
    }
}
