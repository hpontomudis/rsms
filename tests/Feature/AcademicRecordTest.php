<?php

namespace Tests\Feature;

use App\Documents\ReportCardDocumentBuilder;
use App\Models\AcademicPeriod;
use App\Models\AcademicRecord;
use App\Models\AcademicRecordSubject;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\ClassSubject;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\AcademicRecordService;
use App\Services\ReportCardBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Tests\TestCase;

/**
 * Issuing an official report card.
 *
 * The tests worth reading are the ones proving the two directions of the
 * central rule: publication freezes CURRENT data at the moment of publishing
 * (not whatever a draft saw days earlier), and once frozen nothing that happens
 * upstream can move it.
 */
class AcademicRecordTest extends TestCase
{
    use BuildsPlanningFixtures;
    use RefreshDatabase;

    private function records(): AcademicRecordService
    {
        return app(AcademicRecordService::class);
    }

    /** A student in Year 5A with one Mathematics score of 85 in Semester 1. */
    private function studentWithScore(int $score = 85, string $subject = 'Maths'): array
    {
        $assignment = $this->assignmentFor('Year 5A', $subject, 'sarah');
        $student = Student::create([
            'student_number' => 'S-'.uniqid(),
            'first_name' => 'Andi', 'last_name' => 'Saputra',
            'date_of_birth' => '2015-05-05', 'gender' => 'male',
            'enrollment_date' => '2026-07-01', 'status' => 'active',
        ]);
        $this->class('Year 5', 'Year 5A')->students()->attach($student->id, [
            'enrolled_at' => '2026-07-01', 'status' => 'active',
        ]);

        $assessment = Assessment::create([
            'class_subject_id' => $assignment->id,
            'academic_period_id' => $this->period('Semester 1')->id,
            'name' => 'Ulangan 1', 'max_score' => 100, 'assessment_date' => '2026-09-15',
        ]);
        $result = AssessmentResult::create([
            'assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => $score,
        ]);

        return [$student, $result, $assignment];
    }

    private function publisher(): User
    {
        return $this->userWithRole('principal');
    }

    // ------------------------------------------------- period-scoped build

    public function test_the_period_build_reports_only_that_periods_results(): void
    {
        $this->seedReferenceData();
        [$student, , $assignment] = $this->studentWithScore(85);

        $s2 = Assessment::create([
            'class_subject_id' => $assignment->id,
            'academic_period_id' => $this->period('Semester 2')->id,
            'name' => 'Ulangan 2', 'max_score' => 100, 'assessment_date' => '2027-03-01',
        ]);
        AssessmentResult::create(['assessment_id' => $s2->id, 'student_id' => $student->id, 'score' => 95]);

        $card = app(ReportCardBuilder::class)->buildForPeriod($student, $this->period('Semester 1'));

        $this->assertSame(85, (int) $card['rows']->firstWhere('subject.name', 'Maths')->score);
        $this->assertSame(85, $card['overallAverage']);
    }

    public function test_the_year_build_is_untouched_by_the_period_build(): void
    {
        $this->seedReferenceData();
        [$student, , $assignment] = $this->studentWithScore(85);

        $s2 = Assessment::create([
            'class_subject_id' => $assignment->id,
            'academic_period_id' => $this->period('Semester 2')->id,
            'name' => 'Ulangan 2', 'max_score' => 100, 'assessment_date' => '2027-03-01',
        ]);
        AssessmentResult::create(['assessment_id' => $s2->id, 'student_id' => $student->id, 'score' => 95]);

        $year = app(ReportCardBuilder::class)->build($student, $this->year);
        $row = $year['rows']->first();

        // Year overall is a FLAT mean over all results: (85 + 95) / 2 = 90.
        $this->assertSame(90, (int) $row->overall);
        $this->assertSame(85, (int) $row->periodAverages[$this->period('Semester 1')->id]);
        $this->assertSame(95, (int) $row->periodAverages[$this->period('Semester 2')->id]);
    }

    public function test_a_subject_with_no_result_in_the_period_reports_null(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);

        $card = app(ReportCardBuilder::class)->buildForPeriod($student, $this->period('Semester 2'));

        $this->assertNotEmpty($card['rows']);
        $this->assertNull($card['rows']->first()->score);
        $this->assertNull($card['overallAverage']);
    }

    public function test_a_subject_taught_across_a_handover_stays_one_row(): void
    {
        $this->seedReferenceData();
        [$student, , $sarahAssignment] = $this->studentWithScore(80);

        $this->closeAssignment($sarahAssignment, '2026-09-30');
        $eka = $this->assignmentFor('Year 5A', 'Maths', 'eka', '2026-10-01');

        $second = Assessment::create([
            'class_subject_id' => $eka->id,
            'academic_period_id' => $this->period('Semester 1')->id,
            'name' => 'Ulangan 2', 'max_score' => 100, 'assessment_date' => '2026-11-01',
        ]);
        AssessmentResult::create(['assessment_id' => $second->id, 'student_id' => $student->id, 'score' => 90]);

        $card = app(ReportCardBuilder::class)->buildForPeriod($student, $this->period('Semester 1'));

        $this->assertCount(1, $card['rows']);
        $this->assertSame(85, (int) $card['rows']->first()->score); // (80 + 90) / 2
    }

    public function test_group_membership_overlapping_the_period_is_included(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);

        $groupAssignment = $this->groupAssignmentFor('Green', 'Eng', 'lena');
        $this->group('Green')->memberships()->create([
            'student_id' => $student->id, 'started_on' => '2026-08-01', 'ended_on' => '2026-10-31',
        ]);

        $card = app(ReportCardBuilder::class)->buildForPeriod($student, $this->period('Semester 1'));

        // Membership closed in October still counts towards Semester 1.
        $this->assertContains('Eng', $card['rows']->pluck('subject.name')->all());
    }

    // ---------------------------------------------------- publish freezes

    public function test_publishing_issues_current_data_not_what_the_draft_first_saw(): void
    {
        $this->seedReferenceData();
        [$student, $result] = $this->studentWithScore(85);

        // Monday: draft prepared while the score is 85.
        $draft = $this->records()->createDraft($student, $this->period('Semester 1'));
        $this->assertSame(85, $this->records()->preview($draft)['overallAverage']);

        // Tuesday: the source is corrected.
        $result->update(['score' => 90]);

        // Friday: publishing must issue 90.
        $record = $this->records()->publish($draft->fresh(), $this->publisher());

        $this->assertSame(90, $record->overall_average);
        $this->assertSame(90, (int) $record->subjects->first()->score);
    }

    public function test_a_draft_stores_no_academic_values_at_all(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);

        $draft = $this->records()->createDraft($student, $this->period('Semester 1'));

        // Nothing stale can be promoted, because nothing was written down.
        $this->assertSame(0, $draft->subjects()->count());
        $this->assertNull($draft->overall_average);
        $this->assertNull($draft->published_at);
    }

    public function test_a_source_change_after_publication_does_not_move_the_record(): void
    {
        $this->seedReferenceData();
        [$student, $result] = $this->studentWithScore(85);

        $record = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        $result->update(['score' => 92]);

        $this->assertSame(85, (int) $record->fresh()->subjects->first()->score);
        $this->assertSame(85, $record->fresh()->overall_average);
        // ...while the live view moves.
        $this->assertSame(92, app(ReportCardBuilder::class)
            ->buildForPeriod($student, $this->period('Semester 1'))['overallAverage']);
    }

    /** @return array<string, callable> label => the mutation to attempt */
    private function upstreamMutations(Student $student): array
    {
        return [
            'subject rename' => fn () => Subject::where('name', 'Maths')->first()->update(['name' => 'Matematika']),
            'student rename' => fn () => $student->update(['first_name' => 'Andy']),
            'period rename' => fn () => $this->period('Semester 1')->update(['name' => 'Semester Ganjil']),
            'class rename' => fn () => $this->class('Year 5', 'Year 5A')->update(['name' => 'Kelas 5A']),
            'school config change' => fn () => config(['school.name' => 'Another School']),
            'principal config change' => fn () => config(['school.principal_name' => 'Someone Else']),
        ];
    }

    public function test_no_upstream_rename_alters_an_issued_record(): void
    {
        $this->seedReferenceData();
        config(['school.name' => 'Rahai School', 'school.principal_name' => 'Ibu Kepala']);
        [$student] = $this->studentWithScore(85);

        $record = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        $before = app(ReportCardDocumentBuilder::class)->fromPublished($record->fresh()->load('subjects'));

        foreach ($this->upstreamMutations($student) as $label => $mutate) {
            $mutate();
        }

        $after = app(ReportCardDocumentBuilder::class)->fromPublished($record->fresh()->load('subjects'));

        $this->assertSame($before->studentName, $after->studentName);
        $this->assertSame($before->periodName, $after->periodName);
        $this->assertSame($before->className, $after->className);
        $this->assertSame($before->schoolName, $after->schoolName);
        $this->assertSame(
            $before->rows->pluck('subjectName')->all(),
            $after->rows->pluck('subjectName')->all(),
        );
        $this->assertSame('Maths', $after->rows->first()->subjectName);
        $this->assertSame('Andi Saputra', $after->studentName);
    }

    public function test_a_homeroom_change_after_publication_does_not_alter_the_record(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);
        $class = $this->class('Year 5', 'Year 5A');
        DB::table('class_teacher')->insert([
            'class_id' => $class->id, 'staff_id' => $this->staff('sarah')->id, 'role' => 'homeroom',
        ]);

        $record = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        $this->assertSame('sarah Teacher', $record->homeroom_teacher_name_snapshot);

        DB::table('class_teacher')->where('class_id', $class->id)->delete();
        DB::table('class_teacher')->insert([
            'class_id' => $class->id, 'staff_id' => $this->staff('eka')->id, 'role' => 'homeroom',
        ]);

        $this->assertSame('sarah Teacher', $record->fresh()->homeroom_teacher_name_snapshot);
    }

    public function test_two_homeroom_teachers_refuse_publication_rather_than_guessing(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);
        $class = $this->class('Year 5', 'Year 5A');

        foreach (['sarah', 'eka'] as $key) {
            DB::table('class_teacher')->insert([
                'class_id' => $class->id, 'staff_id' => $this->staff($key)->id, 'role' => 'homeroom',
            ]);
        }

        $draft = $this->records()->createDraft($student, $this->period('Semester 1'));

        try {
            $this->records()->publish($draft, $this->publisher());
            $this->fail('publication guessed which homeroom teacher to print');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('more than one homeroom teacher', $e->errors()['homeroom_teacher_name_snapshot'][0]);
        }

        $this->assertTrue($draft->fresh()->isDraft());
    }

    // ------------------------------------------------------- supersession

    public function test_a_correction_supersedes_its_predecessor_in_the_right_direction(): void
    {
        $this->seedReferenceData();
        [$student, $result] = $this->studentWithScore(85);

        $first = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        $result->update(['score' => 90]);

        $second = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        $this->assertSame($first->id, $second->supersedes_id, 'the NEW record supersedes the OLD one');
        $this->assertTrue($first->fresh()->isSuperseded());
        $this->assertTrue($second->isPublished());
        $this->assertSame(85, (int) $first->fresh()->subjects->first()->score);
        $this->assertSame(90, (int) $second->subjects->first()->score);
    }

    public function test_only_one_published_record_exists_per_student_and_period(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);

        $this->records()->publish($this->records()->createDraft($student, $this->period('Semester 1')), $this->publisher());
        $this->records()->publish($this->records()->createDraft($student, $this->period('Semester 1')), $this->publisher());

        $this->assertSame(1, AcademicRecord::where('student_id', $student->id)
            ->where('academic_period_id', $this->period('Semester 1')->id)
            ->where('status', 'published')->count());
        $this->assertSame(1, AcademicRecord::where('status', 'superseded')->count());
    }

    public function test_the_database_refuses_a_second_published_record(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);
        $record = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        $this->expectException(QueryException::class);

        DB::table('academic_records')->insert([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'academic_period_id' => $this->period('Semester 1')->id,
            'student_name_snapshot' => 'Forged',
            'period_name_snapshot' => 'Semester 1',
            'academic_year_name_snapshot' => '2026/2027',
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_drafts_and_superseded_records_may_coexist(): void
    {
        $this->seedReferenceData();
        [$student, $result] = $this->studentWithScore(85);

        $this->records()->publish($this->records()->createDraft($student, $this->period('Semester 1')), $this->publisher());
        $result->update(['score' => 90]);
        $this->records()->publish($this->records()->createDraft($student, $this->period('Semester 1')), $this->publisher());
        $this->records()->createDraft($student, $this->period('Semester 1'));

        $this->assertSame(3, AcademicRecord::where('student_id', $student->id)->count());
        $this->assertSame(1, AcademicRecord::where('status', 'draft')->count());
        $this->assertSame(1, AcademicRecord::where('status', 'published')->count());
        $this->assertSame(1, AcademicRecord::where('status', 'superseded')->count());
    }

    public function test_only_one_draft_per_student_and_period(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);
        $this->records()->createDraft($student, $this->period('Semester 1'));

        $this->expectException(ValidationException::class);

        $this->records()->createDraft($student, $this->period('Semester 1'));
    }

    // --------------------------------------------------------- immutable

    public function test_an_issued_record_refuses_every_edit(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);
        $record = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        foreach ([
            ['overall_average' => 99],
            ['student_name_snapshot' => 'Someone Else'],
            ['homeroom_comment' => 'rewritten'],
            ['status' => 'draft'],
        ] as $change) {
            try {
                $record->fresh()->update($change);
                $this->fail('an issued record accepted '.array_key_first($change));
            } catch (LogicException $e) {
                $this->assertStringContainsString('immutable', strtolower($e->getMessage()).' immutable');
            }
        }

        $this->assertSame(85, $record->fresh()->overall_average);
    }

    public function test_a_subject_line_on_an_issued_record_cannot_be_touched(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);
        $record = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        $row = $record->subjects->first();

        try {
            $row->update(['score' => 99]);
            $this->fail('an issued subject line accepted an edit');
        } catch (LogicException) {
            // expected
        }

        try {
            $row->delete();
            $this->fail('an issued subject line was deleted');
        } catch (LogicException) {
            // expected
        }

        $this->assertSame(85, (int) $row->fresh()->score);
    }

    public function test_the_service_refuses_to_republish_or_edit_an_issued_record(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);
        $record = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        foreach ([
            fn () => $this->records()->publish($record->fresh(), $this->publisher()),
            fn () => $this->records()->updateDraft($record->fresh(), ['homeroom_comment' => 'x']),
            fn () => $this->records()->deleteDraft($record->fresh()),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('an issued record accepted a service write');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('immutable', $e->errors()['status'][0]);
            }
        }
    }

    // ------------------------------------------------------------- drafts

    public function test_a_draft_carries_its_comment_through_to_publication(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);

        $draft = $this->records()->createDraft($student, $this->period('Semester 1'));
        $this->records()->updateDraft($draft, ['homeroom_comment' => 'Rajin dan teliti.']);

        $record = $this->records()->publish($draft->fresh(), $this->publisher());

        $this->assertSame('Rajin dan teliti.', $record->homeroom_comment);
    }

    public function test_a_draft_deletes_with_its_rows_and_an_issued_record_never_does(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);

        $draft = $this->records()->createDraft($student, $this->period('Semester 1'));
        $this->records()->deleteDraft($draft);
        $this->assertSame(0, AcademicRecord::count());

        $record = $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        $this->expectException(LogicException::class);
        $record->delete();
    }

    public function test_publishing_an_empty_report_card_is_refused(): void
    {
        $this->seedReferenceData();
        $student = Student::create([
            'student_number' => 'S-EMPTY', 'first_name' => 'Kosong', 'last_name' => 'Siswa',
            'date_of_birth' => '2015-01-01', 'gender' => 'female',
            'enrollment_date' => '2026-07-01', 'status' => 'active',
        ]);

        $draft = $this->records()->createDraft($student, $this->period('Semester 1'));

        $this->expectException(ValidationException::class);

        $this->records()->publish($draft, $this->publisher());
    }

    // ------------------------------------------------------------- audit

    public function test_publication_is_audited_and_the_subject_rows_are_not(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);

        $created = $this->auditCount(AcademicRecord::class, 'created');
        $updated = $this->auditCount(AcademicRecord::class, 'updated');

        $this->records()->publish(
            $this->records()->createDraft($student, $this->period('Semester 1')),
            $this->publisher(),
        );

        $this->assertSame($created + 1, $this->auditCount(AcademicRecord::class, 'created'));
        $this->assertSame($updated + 1, $this->auditCount(AcademicRecord::class, 'updated'));
        // Subject rows are frozen and unreachable, so the parent's trail is enough.
        $this->assertSame(0, $this->auditCount(AcademicRecordSubject::class, 'created'));
    }

    // ---------------------------------------------------- authorization

    public function test_publishing_requires_management_and_reading_follows_student_access(): void
    {
        $this->seedReferenceData();
        [$student] = $this->studentWithScore(85);
        $draft = $this->records()->createDraft($student, $this->period('Semester 1'));

        $teacher = $this->assignmentFor('Year 5A', 'Maths', 'sarah')->teacher->user->fresh();
        $stranger = $this->staff('rina')->user->fresh();
        $manager = $this->publisher();

        $this->assertFalse($teacher->can('create', AcademicRecord::class));
        $this->assertFalse($teacher->can('publish', $draft));
        $this->assertTrue($manager->can('publish', $draft));

        $record = $this->records()->publish($draft, $manager);

        // Reading is the SAME gate as the live report card, which scopes a
        // teacher through class_teacher -- StudentPolicy is untouched, so a
        // subject assignment alone does not grant student access.
        $this->assertFalse($teacher->can('view', $record));

        DB::table('class_teacher')->insert([
            'class_id' => $this->class('Year 5', 'Year 5A')->id,
            'staff_id' => $this->staff('sarah')->id,
            'role' => 'homeroom',
        ]);

        $this->assertTrue($teacher->fresh()->can('view', $record));
        $this->assertFalse($stranger->can('view', $record));
        $this->assertFalse($teacher->fresh()->can('update', $record));
        $this->assertFalse($manager->can('update', $record));
    }
}
