<?php

namespace Tests\Feature\Concerns;

use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\Student;
use App\Models\TeachingGroup;
use App\Models\User;
use App\Services\ClassTeacherService;
use App\Services\CommunicationService;
use Database\Seeders\StaffCategorySeeder;

/**
 * Students, Guardians and class/group enrolment on top of
 * BuildsPlanningFixtures' Class/TeachingGroup/ClassSubject graph -- the
 * minimum shape Communication audience rules resolve against.
 */
trait BuildsCommunicationFixtures
{
    use BuildsPlanningFixtures;

    protected function seedCommunicationReferenceData(): void
    {
        $this->seedReferenceData();
        $this->seed(StaffCategorySeeder::class);
    }

    protected function communications(): CommunicationService
    {
        return app(CommunicationService::class);
    }

    protected function studentNamed(string $first, string $last, string $number): Student
    {
        return Student::firstOrCreate(
            ['student_number' => $number],
            [
                'first_name' => $first, 'last_name' => $last, 'date_of_birth' => '2015-01-01',
                'gender' => 'male', 'enrollment_date' => '2026-07-01', 'status' => 'active',
            ],
        );
    }

    protected function guardianNamed(string $first, string $last, string $phone, ?int $userId = null): Guardian
    {
        return Guardian::firstOrCreate(
            ['phone' => $phone],
            ['first_name' => $first, 'last_name' => $last, 'user_id' => $userId],
        );
    }

    protected function enroll(Student $student, SchoolClass $class): void
    {
        if (! $class->students()->where('student_id', $student->id)->exists()) {
            $class->students()->attach($student->id, ['enrolled_at' => '2026-07-01', 'status' => 'active']);
        }
    }

    protected function linkGuardian(Student $student, Guardian $guardian, string $relationship = 'father'): void
    {
        if (! $student->guardians()->where('guardian_id', $guardian->id)->exists()) {
            $student->guardians()->attach($guardian->id, ['relationship_type' => $relationship, 'is_primary_contact' => true]);
        }
    }

    protected function addToGroup(Student $student, TeachingGroup $group, string $from = '2026-07-01'): void
    {
        \App\Models\TeachingGroupStudent::firstOrCreate(
            ['teaching_group_id' => $group->id, 'student_id' => $student->id, 'ended_on' => null],
            ['started_on' => $from],
        );
    }

    /**
     * Assign the class's homeroom teacher via the real service, not a raw
     * pivot attach -- Foundation F2 made ClassTeacherService the only write
     * path, and going through it here means fixtures exercise the same
     * transactional close-and-create a real handover does (see
     * handoverHomeroom() below).
     */
    protected function assignHomeroom(SchoolClass $class, Staff $staff): void
    {
        app(ClassTeacherService::class)->setHomeroom($class, $staff);
    }

    /**
     * A real handover, distinct from assignHomeroom() only in naming --
     * makes tests read as "this call is deliberately replacing the current
     * teacher" rather than "this is the class's first assignment".
     */
    protected function handoverHomeroom(SchoolClass $class, Staff $staff): void
    {
        app(ClassTeacherService::class)->setHomeroom($class, $staff);
    }

    protected function teacherStaff(string $key): Staff
    {
        return $this->staff($key);
    }

    protected function principalUser(): User
    {
        return $this->userWithRole('principal');
    }

    protected function managementUser(): User
    {
        return $this->userWithRole('management');
    }

    protected function adminStaffUser(): User
    {
        return $this->userWithRole('admin_staff');
    }

    protected function teacherUserFor(string $key): User
    {
        return $this->staff($key)->user->fresh();
    }

    protected function staffCategory(string $code = 'teacher'): StaffCategory
    {
        return StaffCategory::where('code', $code)->firstOrFail();
    }
}
