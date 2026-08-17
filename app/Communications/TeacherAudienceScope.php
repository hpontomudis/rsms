<?php

namespace App\Communications;

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
 * CLASS AUTHORITY is the union of two independent signals this codebase has,
 * both now genuinely effective-dated (Foundation F2, following Step 0's
 * class_subject precedent):
 *
 *  - `class_teacher` (homeroom/assistant) -- an OPEN row (`ended_on IS
 *    NULL`) grants authority; a closed one does not. Before Foundation F2,
 *    "current" was only approximated by the row's Class belonging to the
 *    current academic year, which caught cross-year staleness but NOT a
 *    mid-year handover left half-done (`Classes\Show::assignTeacher()`/
 *    `removeTeacher()` were two independent, non-transactional actions).
 *    That gap is closed: `ClassTeacherService::setHomeroom()` is now the
 *    only write path, closes the outgoing row and opens the new one in one
 *    transaction, and `class_teacher_homeroom_open_unique` makes two
 *    simultaneously-open homeroom rows for one class a database-level
 *    impossibility. `subject_teacher` rows are excluded here (deprecated,
 *    not authoritative for anything -- `ClassSubject` is canonical for
 *    subject teaching).
 *  - `class_subject` (subject-level teaching) IS effective-dated
 *    (`ClassSubject::active()`); a closed assignment grants no authority,
 *    matching TeachingModulePolicy/AssessmentPolicy's established rule.
 *    `Classes\Show::assignSubject()` always closes the current assignment
 *    inside the same transaction that opens the new one -- close-and-create
 *    is structurally enforced, not merely conventional, the same shape
 *    `ClassTeacherService::setHomeroom()` now follows.
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
 *
 * STUDENT AUTHORITY via class membership (`authorizedStudentIds()`) is now
 * effective-dated too (Foundation F3): only a Student's OPEN `class_student`
 * row (`status = 'active' AND ended_on IS NULL`) counts. A Student
 * transferred out of an authorized Class loses current audience membership
 * for it the moment the transfer commits (`ClassStudentService::transfer()`
 * is transactional close-and-create), and a Student transferred IN gains it
 * immediately -- a historical (closed) `class_student` row never grants
 * current audience membership, the same discipline F2 already applies to
 * `class_teacher`.
 */
class TeacherAudienceScope
{
    public function authorizedClassIds(Staff $teacher): Collection
    {
        $viaClassTeacher = ClassTeacher::where('staff_id', $teacher->id)
            ->whereIn('role', ['homeroom', 'assistant'])
            ->open()
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
            : ClassStudent::whereIn('class_id', $classIds)->where('status', 'active')->whereNull('ended_on')->pluck('student_id');

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
