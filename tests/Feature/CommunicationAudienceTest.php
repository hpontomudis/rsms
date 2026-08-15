<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Concerns\BuildsCommunicationFixtures;
use Tests\TestCase;

/**
 * Every rule_type, resolved for a Principal (unscoped) and re-checked for a
 * Teacher (scoped to their own current Classes/Teaching Groups and the
 * students/guardians within them -- never school-wide, never Staff Category,
 * never role, never an arbitrary Staff/User/Class/Group/Student/Guardian).
 */
class CommunicationAudienceTest extends TestCase
{
    use BuildsCommunicationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommunicationReferenceData();
    }

    // --------------------------------------------------- principal, unscoped

    public function test_principal_may_target_everyone(): void
    {
        $principal = $this->principalUser();
        $this->teacherStaff('Sarah');
        $communication = $this->draft($principal);

        $rule = $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'everyone']);

        $this->assertSame('everyone', $rule->rule_type);
    }

    public function test_principal_may_target_all_staff(): void
    {
        $principal = $this->principalUser();
        $communication = $this->draft($principal);

        $rule = $this->communications()->addAudienceRule($communication, $principal, ['rule_type' => 'all_staff']);

        $this->assertSame('all_staff', $rule->rule_type);
    }

    public function test_principal_may_target_a_staff_category(): void
    {
        $principal = $this->principalUser();
        $communication = $this->draft($principal);

        $rule = $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'staff_category', 'staff_category_id' => $this->staffCategory()->id,
        ]);

        $this->assertSame($this->staffCategory()->id, $rule->staff_category_id);
    }

    public function test_principal_may_target_a_role(): void
    {
        $principal = $this->principalUser();
        $communication = $this->draft($principal);

        $rule = $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'role', 'role_name' => 'teacher',
        ]);

        $this->assertSame('teacher', $rule->role_name);
    }

    public function test_principal_may_target_selected_staff(): void
    {
        $principal = $this->principalUser();
        $staff = $this->teacherStaff('Sarah');
        $communication = $this->draft($principal);

        $rule = $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'selected_staff', 'ids' => [$staff->id],
        ]);

        $this->assertSame(1, $rule->selectedStaff()->count());
    }

    public function test_principal_may_target_selected_users(): void
    {
        $principal = $this->principalUser();
        $communication = $this->draft($principal);

        $rule = $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'selected_users', 'ids' => [$this->managementUser()->id],
        ]);

        $this->assertSame(1, $rule->selectedUsers()->count());
    }

    public function test_principal_may_target_any_class(): void
    {
        $principal = $this->principalUser();
        $class = $this->class('Year 5', 'Year 5A');
        $communication = $this->draft($principal);

        $rule = $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'school_class_students', 'school_class_id' => $class->id,
        ]);

        $this->assertSame($class->id, $rule->school_class_id);
    }

    public function test_principal_may_target_any_teaching_group(): void
    {
        $principal = $this->principalUser();
        $group = $this->group('Green');
        $communication = $this->draft($principal);

        $rule = $this->communications()->addAudienceRule($communication, $principal, [
            'rule_type' => 'teaching_group_students', 'teaching_group_id' => $group->id,
        ]);

        $this->assertSame($group->id, $rule->teaching_group_id);
    }

    // --------------------------------------------------------- teacher scope

    public function test_teacher_may_target_a_class_they_currently_teach(): void
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $rule = $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'school_class_students', 'school_class_id' => $assignment->class_id,
        ]);

        $this->assertSame($assignment->class_id, $rule->school_class_id);
    }

    public function test_teacher_is_refused_an_unrelated_class(): void
    {
        $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $unrelatedClass = $this->class('Year 6', 'Year 6A');
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'school_class_students', 'school_class_id' => $unrelatedClass->id,
        ]);
    }

    public function test_teacher_is_refused_a_historical_closed_assignment_class(): void
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $this->closeAssignment($assignment, '2026-08-01');
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'school_class_students', 'school_class_id' => $assignment->class_id,
        ]);
    }

    public function test_teacher_may_target_a_class_via_homeroom_even_without_a_class_subject_assignment(): void
    {
        // class_teacher has no effective dating of its own; "current" is
        // approximated by the class belonging to the current academic year --
        // see TeacherAudienceScope's docblock for the documented limitation.
        $staff = $this->teacherStaff('Sarah');
        $class = $this->class('Year 5', 'Year 5A');
        $this->assignHomeroom($class, $staff);
        $teacher = $staff->user->fresh();
        $communication = $this->draft($teacher);

        $rule = $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'school_class_students', 'school_class_id' => $class->id,
        ]);

        $this->assertSame($class->id, $rule->school_class_id);
    }

    /**
     * Documents a real, confirmed gap rather than papering over it: unlike
     * ClassSubject (where Classes\Show::assignSubject() closes the outgoing
     * assignment atomically), ClassTeacher's handover is two independent
     * admin actions (removeTeacher() then assignTeacher()). If the first is
     * skipped, the outgoing homeroom teacher's row is never removed and
     * TeacherAudienceScope has no way to tell it apart from the new one --
     * both are "current" by its own (necessarily approximate) definition.
     * This test is not a bug report to fix in this closeout; it exists so a
     * future change to class_teacher's shape has to consciously update this
     * assertion rather than silently making the limitation disappear or worse.
     */
    public function test_a_stale_unremoved_homeroom_row_retains_authority_after_an_incomplete_handover(): void
    {
        $outgoing = $this->teacherStaff('Sarah');
        $incoming = $this->teacherStaff('Eka');
        $class = $this->class('Year 5', 'Year 5A');
        $this->assignHomeroom($class, $outgoing);

        // The handover assigns the new homeroom teacher but -- realistically,
        // by omission -- never calls removeTeacher() on the outgoing one.
        $this->assignHomeroom($class, $incoming);

        $outgoingTeacherUser = $outgoing->user->fresh();
        $communication = $this->draft($outgoingTeacherUser);

        // Confirmed gap: the outgoing teacher can still target this class.
        $rule = $this->communications()->addAudienceRule($communication, $outgoingTeacherUser, [
            'rule_type' => 'school_class_students', 'school_class_id' => $class->id,
        ]);

        $this->assertSame($class->id, $rule->school_class_id);
    }

    public function test_teacher_may_target_a_teaching_group_they_actively_teach(): void
    {
        $assignment = $this->groupAssignmentFor('Green', 'Eng', 'Sarah');
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $rule = $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'teaching_group_students', 'teaching_group_id' => $assignment->teaching_group_id,
        ]);

        $this->assertSame($assignment->teaching_group_id, $rule->teaching_group_id);
    }

    public function test_teacher_is_refused_an_unrelated_teaching_group(): void
    {
        $this->groupAssignmentFor('Green', 'Eng', 'Sarah');
        $unrelatedGroup = $this->group('Blue');
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'teaching_group_students', 'teaching_group_id' => $unrelatedGroup->id,
        ]);
    }

    public function test_teacher_is_refused_a_historical_teaching_group_assignment(): void
    {
        $assignment = $this->groupAssignmentFor('Green', 'Eng', 'Sarah');
        $this->closeAssignment($assignment, '2026-08-01');
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'teaching_group_students', 'teaching_group_id' => $assignment->teaching_group_id,
        ]);
    }

    public function test_teacher_may_target_an_authorized_student(): void
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $this->enroll($student, $assignment->schoolClass);
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $rule = $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'selected_students', 'ids' => [$student->id],
        ]);

        $this->assertSame(1, $rule->selectedStudents()->count());
    }

    public function test_teacher_is_refused_an_unrelated_student(): void
    {
        $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $unrelatedStudent = $this->studentNamed('Citra', 'Putri', 'STU-2');
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'selected_students', 'ids' => [$unrelatedStudent->id],
        ]);
    }

    public function test_teacher_may_target_an_authorized_guardian(): void
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $this->enroll($student, $assignment->schoolClass);
        $guardian = $this->guardianNamed('Rudi', 'Wijaya', '0812-1');
        $this->linkGuardian($student, $guardian);
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $rule = $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'selected_guardians', 'ids' => [$guardian->id],
        ]);

        $this->assertSame(1, $rule->selectedGuardians()->count());
    }

    public function test_teacher_is_refused_an_unrelated_guardian(): void
    {
        $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $otherStudent = $this->studentNamed('Citra', 'Putri', 'STU-2');
        $unrelatedGuardian = $this->guardianNamed('Sri', 'Putri', '0812-2');
        $this->linkGuardian($otherStudent, $unrelatedGuardian);
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'selected_guardians', 'ids' => [$unrelatedGuardian->id],
        ]);
    }

    public function test_teacher_is_refused_a_school_wide_rule(): void
    {
        $teacher = $this->teacherUserFor('Sarah');
        $this->teacherStaff('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, ['rule_type' => 'everyone']);
    }

    public function test_teacher_is_refused_an_all_staff_rule(): void
    {
        $teacher = $this->teacherUserFor('Sarah');
        $this->teacherStaff('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, ['rule_type' => 'all_staff']);
    }

    public function test_teacher_is_refused_a_staff_category_rule(): void
    {
        $teacher = $this->teacherUserFor('Sarah');
        $this->teacherStaff('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'staff_category', 'staff_category_id' => $this->staffCategory()->id,
        ]);
    }

    public function test_teacher_is_refused_a_role_rule(): void
    {
        $teacher = $this->teacherUserFor('Sarah');
        $this->teacherStaff('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, ['rule_type' => 'role', 'role_name' => 'teacher']);
    }

    public function test_teacher_is_refused_arbitrary_selected_staff(): void
    {
        $teacher = $this->teacherUserFor('Sarah');
        $otherStaff = $this->teacherStaff('Budi');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'selected_staff', 'ids' => [$otherStaff->id],
        ]);
    }

    public function test_teacher_is_refused_arbitrary_selected_users(): void
    {
        $teacher = $this->teacherUserFor('Sarah');
        $this->teacherStaff('Sarah');
        $communication = $this->draft($teacher);

        $this->expectException(ValidationException::class);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'selected_users', 'ids' => [$this->managementUser()->id],
        ]);
    }

    public function test_scope_is_re_validated_at_publish_not_only_at_rule_add_time(): void
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'Sarah');
        $student = $this->studentNamed('Evelyn', 'Wijaya', 'STU-1');
        $this->enroll($student, $assignment->schoolClass);
        $teacher = $this->teacherUserFor('Sarah');
        $communication = $this->draft($teacher);
        $this->communications()->addAudienceRule($communication, $teacher, [
            'rule_type' => 'school_class_students', 'school_class_id' => $assignment->class_id,
        ]);

        // The assignment closes AFTER the rule was legitimately added.
        $this->closeAssignment($assignment->fresh(), '2026-08-01');

        $this->expectException(ValidationException::class);
        $this->communications()->publish($communication->fresh(), $teacher);
    }

    private function draft($actor)
    {
        return $this->communications()->createDraft($actor, [
            'display_sender' => 'Test', 'title' => 'Test', 'body' => 'Body.',
        ]);
    }
}
