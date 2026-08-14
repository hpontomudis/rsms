<?php

namespace App\Communications;

use App\Models\AcademicYear;
use App\Models\ClassStudent;
use App\Models\ClassSubject;
use App\Models\ClassTeacher;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\TeachingGroupStudent;
use Illuminate\Support\Collection;

/**
 * What a teacher may currently target, reused identically by
 * CommunicationPolicy (authorization) and CommunicationService (validation
 * at both audience-rule-add time and publish time -- never UI-filtering
 * alone, per the V8A architecture review).
 *
 * CLASS AUTHORITY is the union of two independent, unsynced signals this
 * codebase already has, documented here rather than papered over:
 *
 *  - `class_teacher` (homeroom/assistant/subject_teacher) has NO effective
 *    dating of its own -- a row persists forever once created. "Current" is
 *    approximated by requiring the row's Class to belong to the CURRENT
 *    academic year, since classes are created fresh each year and a past
 *    year's class is naturally a different row.
 *  - `class_subject` (subject-level teaching) IS effective-dated
 *    (`ClassSubject::active()`); a closed assignment grants no authority,
 *    matching TeachingModulePolicy/AssessmentPolicy's established rule.
 *
 * These two tables were built in different phases and are never kept in
 * sync with each other -- a teacher can appear in one and not the other for
 * the same class. Both are treated as genuine evidence of current teaching
 * responsibility; neither alone is trusted as complete.
 *
 * TEACHING GROUP AUTHORITY has exactly one source: `class_subject` is the
 * only assignment mechanism Teaching Groups have (no separate pivot table),
 * so `ClassSubject::active()->teachingGroupBacked()` is authoritative and
 * unambiguous.
 */
class TeacherAudienceScope
{
    public function authorizedClassIds(Staff $teacher): Collection
    {
        $currentYearId = AcademicYear::current()?->id;

        $viaClassTeacher = $currentYearId === null
            ? collect()
            : ClassTeacher::where('staff_id', $teacher->id)
                ->whereHas('schoolClass', fn ($q) => $q->where('academic_year_id', $currentYearId))
                ->pluck('class_id');

        $viaClassSubject = ClassSubject::active()->classBacked()
            ->where('staff_id', $teacher->id)
            ->pluck('class_id');

        return $viaClassTeacher->concat($viaClassSubject)->unique()->values();
    }

    public function authorizedTeachingGroupIds(Staff $teacher): Collection
    {
        return ClassSubject::active()->teachingGroupBacked()
            ->where('staff_id', $teacher->id)
            ->pluck('teaching_group_id');
    }

    public function authorizedStudentIds(Staff $teacher): Collection
    {
        $classIds = $this->authorizedClassIds($teacher);
        $groupIds = $this->authorizedTeachingGroupIds($teacher);

        $viaClasses = $classIds->isEmpty()
            ? collect()
            : ClassStudent::whereIn('class_id', $classIds)->where('status', 'active')->pluck('student_id');

        $viaGroups = $groupIds->isEmpty()
            ? collect()
            : TeachingGroupStudent::whereIn('teaching_group_id', $groupIds)->whereNull('ended_on')->pluck('student_id');

        return $viaClasses->concat($viaGroups)->unique()->values();
    }

    public function authorizedGuardianIds(Staff $teacher): Collection
    {
        $studentIds = $this->authorizedStudentIds($teacher);

        if ($studentIds->isEmpty()) {
            return collect();
        }

        return Guardian::whereHas('students', fn ($q) => $q->whereIn('students.id', $studentIds))->pluck('id');
    }

    public function canTargetClass(Staff $teacher, int $classId): bool
    {
        return $this->authorizedClassIds($teacher)->contains($classId);
    }

    public function canTargetTeachingGroup(Staff $teacher, int $teachingGroupId): bool
    {
        return $this->authorizedTeachingGroupIds($teacher)->contains($teachingGroupId);
    }

    public function canTargetStudent(Staff $teacher, int $studentId): bool
    {
        return $this->authorizedStudentIds($teacher)->contains($studentId);
    }

    public function canTargetGuardian(Staff $teacher, int $guardianId): bool
    {
        return $this->authorizedGuardianIds($teacher)->contains($guardianId);
    }
}
