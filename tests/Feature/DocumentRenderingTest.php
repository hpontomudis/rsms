<?php

namespace Tests\Feature;

use App\Documents\AnnualProgrammeDocumentBuilder;
use App\Documents\DailyJournalDocumentBuilder;
use App\Documents\LearningPathwayDocumentBuilder;
use App\Documents\ReportCardDocumentBuilder;
use App\Documents\SemesterProgrammeDocumentBuilder;
use App\Documents\TeachingModuleDocumentBuilder;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\CurriculumScope;
use App\Models\LearningObjective;
use App\Models\Student;
use App\Models\Subject;
use App\Services\AcademicRecordService;
use App\Services\DailyJournalService;
use App\Services\SemesterProgrammeService;
use App\Services\TeachingModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Tests\TestCase;

/**
 * The printed documents.
 *
 * The load-bearing test here is the one proving an issued report card renders
 * from snapshots alone: every upstream label is mutated and the rendered HTML
 * has to come back byte-identical.
 */
class DocumentRenderingTest extends TestCase
{
    use BuildsPlanningFixtures;
    use RefreshDatabase;

    private function activeScope(string $phaseCode): CurriculumScope
    {
        $scope = $this->scopeFor($phaseCode);
        $this->restoreActive($this->curriculum());

        return $scope;
    }

    private function studentWithScore(int $score = 85, string $firstName = 'Andi', string $lastName = 'Saputra'): Student
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $student = Student::create([
            'student_number' => 'S-'.uniqid(), 'first_name' => $firstName, 'last_name' => $lastName,
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
        AssessmentResult::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => $score]);

        // A teacher of record, so the student is reachable and the document has
        // a homeroom name to print.
        DB::table('class_teacher')->insert([
            'class_id' => $this->class('Year 5', 'Year 5A')->id,
            'staff_id' => $this->staff('sarah')->id, 'role' => 'homeroom',
        ]);

        return $student;
    }

    // ------------------------------------------------------- report card

    public function test_the_live_year_document_is_marked_as_a_preview(): void
    {
        $this->seedReferenceData();
        $student = $this->studentWithScore();

        $document = app(ReportCardDocumentBuilder::class)->fromLiveYear($student, $this->year);

        $this->assertTrue($document->isPreview);
        $this->assertTrue($document->showOverallColumn);
        $this->assertSame(['Semester 1', 'Semester 2'], $document->columns);
        $this->assertNull($document->issuedOn);
    }

    public function test_the_live_period_document_has_one_score_column(): void
    {
        $this->seedReferenceData();
        $student = $this->studentWithScore();

        $document = app(ReportCardDocumentBuilder::class)->fromLivePeriod($student, $this->period('Semester 1'));

        $this->assertTrue($document->isPreview);
        $this->assertFalse($document->showOverallColumn);
        $this->assertCount(1, $document->columns);
        $this->assertSame('Semester 1', $document->periodName);
        $this->assertSame([85], $document->rows->first()->scores);
    }

    public function test_the_published_document_is_not_a_preview_and_carries_its_issue_date(): void
    {
        $this->seedReferenceData();
        $student = $this->studentWithScore();

        $record = app(AcademicRecordService::class)->publish(
            app(AcademicRecordService::class)->createDraft($student, $this->period('Semester 1')),
            $this->userWithRole('principal'),
        );

        $document = app(ReportCardDocumentBuilder::class)->fromPublished($record->load('subjects'));

        $this->assertFalse($document->isPreview);
        $this->assertNotNull($document->issuedOn);
        $this->assertSame('sarah Teacher', $document->signatories[0]['name']);
    }

    /** The guarantee, proven by mutating everything upstream. */
    public function test_the_published_document_renders_from_snapshots_alone(): void
    {
        $this->seedReferenceData();
        config(['school.name' => 'Rahai School', 'school.principal_name' => 'Ibu Kepala']);
        $student = $this->studentWithScore(85);
        $class = $this->class('Year 5', 'Year 5A');

        $record = app(AcademicRecordService::class)->publish(
            app(AcademicRecordService::class)->createDraft($student, $this->period('Semester 1')),
            $this->userWithRole('principal'),
        );

        $before = view('documents.report-card', [
            'document' => app(ReportCardDocumentBuilder::class)->fromPublished($record->fresh()->load('subjects')),
        ])->render();

        // Move every source the document could otherwise have read.
        Subject::where('name', 'Maths')->first()->update(['name' => 'Matematika']);
        $student->update(['first_name' => 'Andy', 'last_name' => 'Saputro', 'student_number' => 'CHANGED']);
        $this->period('Semester 1')->update(['name' => 'Semester Ganjil']);
        $class->update(['name' => 'Kelas 5A']);
        $this->year->update(['name' => '9999/0000']);
        AssessmentResult::query()->update(['score' => 10]);
        DB::table('class_teacher')->delete();
        config(['school.name' => 'Another School', 'school.principal_name' => 'Someone Else']);

        $after = view('documents.report-card', [
            'document' => app(ReportCardDocumentBuilder::class)->fromPublished($record->fresh()->load('subjects')),
        ])->render();

        $this->assertSame($before, $after, 'an issued report card changed when its sources moved');
        $this->assertStringContainsString('Maths', $after);
        $this->assertStringContainsString('Andi Saputra', $after);
        $this->assertStringContainsString('Rahai School', $after);
        $this->assertStringNotContainsString('Matematika', $after);
    }

    public function test_the_preview_watermark_is_present_on_live_output_and_absent_on_an_issued_one(): void
    {
        $this->seedReferenceData();
        $student = $this->studentWithScore();

        $live = view('documents.report-card', [
            'document' => app(ReportCardDocumentBuilder::class)->fromLivePeriod($student, $this->period('Semester 1')),
        ])->render();

        $record = app(AcademicRecordService::class)->publish(
            app(AcademicRecordService::class)->createDraft($student, $this->period('Semester 1')),
            $this->userWithRole('principal'),
        );

        $issued = view('documents.report-card', [
            'document' => app(ReportCardDocumentBuilder::class)->fromPublished($record->load('subjects')),
        ])->render();

        $this->assertStringContainsString('not an issued record', $live);
        $this->assertStringNotContainsString('not an issued record', $issued);
    }

    public function test_a_document_prints_school_config_and_a_print_stylesheet(): void
    {
        $this->seedReferenceData();
        config(['school.name' => 'Sekolah Rahai', 'school.line2' => 'Yayasan Test']);
        $student = $this->studentWithScore();

        $html = view('documents.report-card', [
            'document' => app(ReportCardDocumentBuilder::class)->fromLivePeriod($student, $this->period('Semester 1')),
        ])->render();

        $this->assertStringContainsString('Sekolah Rahai', $html);
        $this->assertStringContainsString('Yayasan Test', $html);
        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString('@page', $html);
        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('no-print', $html);
    }

    public function test_a_missing_principal_prints_an_unnamed_signing_line_rather_than_a_guess(): void
    {
        $this->seedReferenceData();
        config(['school.principal_name' => '']);
        $student = $this->studentWithScore();

        $html = view('documents.report-card', [
            'document' => app(ReportCardDocumentBuilder::class)->fromLivePeriod($student, $this->period('Semester 1')),
        ])->render();

        $this->assertStringContainsString('(...........................)', $html);
    }

    public function test_long_names_and_many_subjects_render_without_truncation(): void
    {
        $this->seedReferenceData();
        $longName = str_repeat('Bartholomew ', 5).'Saputra';
        $student = $this->studentWithScore(85, $longName, 'Wijayakusuma');

        $longSubject = Subject::create(['name' => str_repeat('Pendidikan Pancasila dan ', 3).'Kewarganegaraan']);
        $assignment = \App\Models\ClassSubject::create([
            'class_id' => $this->class('Year 5', 'Year 5A')->id,
            'subject_id' => $longSubject->id,
            'staff_id' => $this->staff('eka')->id, 'started_on' => '2026-07-01',
        ]);
        $assessment = Assessment::create([
            'class_subject_id' => $assignment->id,
            'academic_period_id' => $this->period('Semester 1')->id,
            'name' => 'Ulangan', 'max_score' => 100, 'assessment_date' => '2026-09-15',
        ]);
        AssessmentResult::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 70]);

        $document = app(ReportCardDocumentBuilder::class)->fromLivePeriod($student, $this->period('Semester 1'));
        $html = view('documents.report-card', ['document' => $document])->render();

        $this->assertCount(2, $document->rows);
        $this->assertStringContainsString(e($longName.' Wijayakusuma'), $html);
        $this->assertStringContainsString(e($longSubject->name), $html);
    }

    public function test_the_document_follows_the_configured_period_count(): void
    {
        $this->seedReferenceData();
        \App\Models\AcademicPeriod::create([
            'academic_year_id' => $this->year->id, 'name' => 'Semester 3',
            'sequence' => 3, 'start_date' => '2027-07-01', 'end_date' => '2027-08-31',
        ]);
        $student = $this->studentWithScore();

        $document = app(ReportCardDocumentBuilder::class)->fromLiveYear($student, $this->year->fresh());

        $this->assertSame(['Semester 1', 'Semester 2', 'Semester 3'], $document->columns);
        $this->assertCount(3, $document->rows->first()->scores);
    }

    // --------------------------------------------------- planning documents

    private function planningFixture(): array
    {
        $assignment = $this->assignmentFor('Year 5A', 'Maths', 'sarah');
        $pathway = $this->pathway();

        $annual = $this->programmes()->createForClass($this->class('Year 5', 'Year 5A'), $this->subject('Maths'), $pathway);
        $item = $this->programmes()->addItem($annual, $this->pathwayItem(1), $this->period('Semester 1'), 8);
        $this->programmes()->addItem($annual->fresh(), $this->pathwayItem(2), $this->period('Semester 2'), 6);
        $annual = $this->programmes()->activate($annual->fresh());

        $semester = app(SemesterProgrammeService::class)->create($annual, $this->period('Semester 1'));
        app(SemesterProgrammeService::class)->addSlot($semester, $item, ['week_label' => 'Minggu 3', 'planned_lesson_periods' => 4]);
        app(SemesterProgrammeService::class)->addSlot($semester, $item, ['week_label' => 'Minggu 4', 'planned_lesson_periods' => 4]);

        return [$assignment, $annual, $semester->fresh(), $pathway];
    }

    public function test_the_prota_document_groups_by_period_and_shows_jp(): void
    {
        $this->seedReferenceData();
        [, $annual] = $this->planningFixture();

        $document = app(AnnualProgrammeDocumentBuilder::class)->build($annual->fresh());
        $html = view('documents.planning', ['document' => $document])->render();

        $this->assertSame('Program Tahunan (Prota)', $document->title);
        $this->assertSame(['Semester 1', 'Semester 2'], $document->sections->pluck('heading')->all());
        $this->assertStringContainsString('8 JP', $html);
        $this->assertStringContainsString('6 JP', $html);
        $this->assertStringContainsString('Dicetak', $html);
    }

    public function test_the_prosem_document_prints_every_slot_of_one_objective(): void
    {
        $this->seedReferenceData();
        [, , $semester] = $this->planningFixture();

        $document = app(SemesterProgrammeDocumentBuilder::class)->build($semester);
        $html = view('documents.planning', ['document' => $document])->render();

        $this->assertSame('Program Semester (Prosem)', $document->title);
        $this->assertCount(2, $document->sections->first()->rows);
        $this->assertStringContainsString('Minggu 3', $html);
        $this->assertStringContainsString('Minggu 4', $html);
    }

    public function test_the_atp_document_preserves_item_order(): void
    {
        $this->seedReferenceData();
        $pathway = $this->pathway();
        $this->pathwayItem(1);
        $this->pathwayItem(2);

        $document = app(LearningPathwayDocumentBuilder::class)->build($pathway->fresh());

        $this->assertSame('Alur Tujuan Pembelajaran (ATP)', $document->title);
        $this->assertSame(['1', '2'], array_column($document->sections->first()->rows, 0));
    }

    public function test_the_module_document_shows_planned_objectives_and_slots(): void
    {
        $this->seedReferenceData();
        [$assignment, , $semester] = $this->planningFixture();

        $module = app(TeachingModuleService::class)->create($assignment, $this->activeScope('C'), [
            'title' => 'Pecahan', 'planned_activity' => 'Kertas lipat.',
        ]);
        app(TeachingModuleService::class)->linkObjective($module, $this->pathwayItem(1)->learningObjective);
        app(TeachingModuleService::class)->linkSlot($module->fresh(), $semester->items()->first());

        $document = app(TeachingModuleDocumentBuilder::class)->build($module->fresh());
        $html = view('documents.planning', ['document' => $document])->render();

        $this->assertSame('Modul Ajar', $document->title);
        $this->assertStringContainsString('Kertas lipat.', $html);
        $this->assertStringContainsString('Minggu 3', $html);
    }

    public function test_the_journal_document_shows_actual_objectives_and_a_substitute(): void
    {
        $this->seedReferenceData();
        [$assignment] = $this->planningFixture();
        $budi = $this->staff('budi');

        $journal = app(DailyJournalService::class)->create(
            $assignment, $this->activeScope('C'), $this->period('Semester 1'),
            '2026-09-15', $budi,
            ['topic' => 'Pecahan', 'actual_activity' => 'Dua kelompok belum selesai.', 'actual_lesson_periods' => 3],
        );
        app(DailyJournalService::class)->linkObjective($journal, $this->pathwayItem(1)->learningObjective);

        $document = app(DailyJournalDocumentBuilder::class)->build($journal->fresh());
        $html = view('documents.planning', ['document' => $document])->render();

        $this->assertSame('Jurnal Harian Guru', $document->title);
        $this->assertStringContainsString('budi Teacher', $html);
        $this->assertStringContainsString('substitute', $html);
        $this->assertStringContainsString('3 JP', $html);
        $this->assertStringContainsString('Dua kelompok belum selesai.', $html);
        // Assessment activity only -- never a mark.
        $this->assertStringNotContainsString('max_score', $html);
    }

    public function test_an_english_document_uses_english_vocabulary(): void
    {
        $this->seedReferenceData();
        $assignment = $this->groupAssignmentFor('Green', 'Eng', 'lena');
        $scope = $this->englishScope('Green');
        $this->restoreActive($this->englishCurriculum());

        $module = app(TeachingModuleService::class)->create($assignment, $scope, [
            'title' => 'Colours', 'planned_activity' => 'Flashcards.',
        ]);

        $document = app(TeachingModuleDocumentBuilder::class)->build($module);

        $this->assertSame('Teaching Module', $document->title);
    }

    public function test_no_snapshot_tables_were_created_for_planning_documents(): void
    {
        $this->seedReferenceData();

        foreach ([
            'documents', 'document_types', 'document_fields', 'generated_documents',
            'published_annual_programmes', 'published_semester_programmes',
            'published_learning_pathways', 'published_teaching_modules',
        ] as $table) {
            $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable($table),
                "{$table} would duplicate a canonical planning record");
        }
    }
}
