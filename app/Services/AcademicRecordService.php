<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicRecord;
use App\Models\AcademicRecordSubject;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issuing an official report card.
 *
 * THE ONE RULE THAT MATTERS: the historical freeze happens AT PUBLICATION, not
 * when the draft was created.
 *
 * A draft holds only what a human authors -- the homeroom comment and notes --
 * plus the scope it is for. It stores NO subject rows and NO snapshot labels,
 * so there is nothing stale to promote. Its preview reads live data every time.
 * publish() then rebuilds everything from current data inside one transaction.
 *
 * That is what stops the Monday-85 / Friday-90 failure: a draft prepared on
 * Monday and published on Friday issues Friday's numbers, because Monday's were
 * never written down.
 */
class AcademicRecordService
{
    public function __construct(private ReportCardBuilder $builder) {}

    /**
     * Start a draft. Deliberately stores no academic values at all.
     */
    public function createDraft(Student $student, AcademicPeriod $period, array $attributes = []): AcademicRecord
    {
        $year = $period->academicYear;

        if (! $year) {
            $this->fail('academic_period_id', 'That reporting period has no resolvable academic year.');
        }

        $existingDraft = AcademicRecord::where('student_id', $student->id)
            ->where('academic_period_id', $period->id)
            ->where('status', 'draft')
            ->exists();

        if ($existingDraft) {
            $this->fail('academic_period_id', "{$student->fullName()} already has a draft for {$period->name}. Finish or delete it first.");
        }

        return AcademicRecord::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'academic_period_id' => $period->id,
            // Labels are NOT captured here -- they are resolved at publication.
            // A draft that recorded them would be a snapshot pretending to be a
            // working document.
            'student_name_snapshot' => $student->fullName(),
            'period_name_snapshot' => $period->name,
            'academic_year_name_snapshot' => $year->name,
            'homeroom_comment' => $this->orNull($attributes['homeroom_comment'] ?? null),
            'notes' => $this->orNull($attributes['notes'] ?? null),
            'status' => 'draft',
        ]);
    }

    /** Only a draft's authored content may be edited. */
    public function updateDraft(AcademicRecord $record, array $attributes): AcademicRecord
    {
        $this->assertDraft($record);

        $record->update([
            'homeroom_comment' => $this->orNull($attributes['homeroom_comment'] ?? null),
            'notes' => $this->orNull($attributes['notes'] ?? null),
        ]);

        return $record->refresh();
    }

    /**
     * The live preview a draft shows. Current data, every time.
     *
     * @return array{period: AcademicPeriod, rows: \Illuminate\Support\Collection, overallAverage: int|null}
     */
    public function preview(AcademicRecord $record): array
    {
        return $this->builder->buildForPeriod($record->student, $record->academicPeriod);
    }

    /**
     * ISSUE THE DOCUMENT.
     *
     * Rebuilds every academic value and every display label from CURRENT data,
     * writes the subject rows, freezes the identity strings, supersedes any
     * predecessor, and publishes -- all in one transaction. If any step fails,
     * nothing moves: there is never zero current publication, and never two.
     */
    public function publish(AcademicRecord $record, User $publisher): AcademicRecord
    {
        $this->assertDraft($record);

        $student = $record->student;
        $period = $record->academicPeriod;
        $year = $record->academicYear;

        if (! $student || ! $period || ! $year) {
            $this->fail('status', 'This draft no longer resolves to a student and reporting period.');
        }

        // Resolved BEFORE the transaction so an ambiguous homeroom teacher is
        // reported rather than silently resolved to whichever row came first.
        $class = $this->resolveClass($student, $year);
        $homeroom = $this->resolveHomeroomTeacherName($class);

        $card = $this->builder->buildForPeriod($student, $period);

        if ($card['rows']->isEmpty()) {
            $this->fail('status', "There is nothing to issue: {$student->fullName()} has no subjects for {$period->name}.");
        }

        return DB::transaction(function () use ($record, $student, $period, $year, $class, $homeroom, $card, $publisher) {
            // Lock the current publication, if any, before deciding anything.
            $predecessor = AcademicRecord::where('student_id', $student->id)
                ->where('academic_period_id', $period->id)
                ->where('status', 'published')
                ->lockForUpdate()
                ->first();

            // The predecessor steps down BEFORE the replacement steps up. The
            // partial unique index permits exactly one published row, so the
            // other order would collide with itself mid-transaction.
            if ($predecessor) {
                $predecessor->update(['status' => 'superseded']);
            }

            // Subject rows are written ONLY here, from the build just run.
            $record->subjects()->get()->each->delete();

            foreach ($card['rows']->values() as $index => $row) {
                AcademicRecordSubject::create([
                    'academic_record_id' => $record->id,
                    'subject_id' => $row->subject->id,
                    'subject_name_snapshot' => $row->subject->name,
                    'score' => $row->score,
                    'position' => $index + 1,
                ]);
            }

            $record->update([
                // Re-resolved, not trusted from the draft: a name corrected
                // between drafting and issuing should appear on the document.
                'student_name_snapshot' => $student->fullName(),
                'student_number_snapshot' => $student->student_number,
                'class_id' => $class?->id,
                // Point-in-time publication context, NOT reconstructed history:
                // class_student is not effective-dated and cannot prove where a
                // student sat in September.
                'class_name_snapshot' => $class?->name,
                'grade_name_snapshot' => $class?->grade?->name,
                'period_name_snapshot' => $period->name,
                'academic_year_name_snapshot' => $year->name,
                'school_name_snapshot' => config('school.name'),
                'school_line2_snapshot' => $this->orNull(config('school.line2')),
                'school_address_snapshot' => $this->orNull(config('school.address')),
                'principal_name_snapshot' => $this->orNull(config('school.principal_name')),
                'principal_title_snapshot' => $this->orNull(config('school.principal_title')),
                'homeroom_teacher_name_snapshot' => $homeroom,
                'overall_average' => $card['overallAverage'],
                'published_at' => now(),
                'published_by_user_id' => $publisher->id,
                'supersedes_id' => $predecessor?->id,
                // Direction is deliberate: the NEW record supersedes the OLD.
                'status' => 'published',
            ]);

            return $record->refresh();
        });
    }

    public function deleteDraft(AcademicRecord $record): void
    {
        $this->assertDraft($record);

        DB::transaction(function () use ($record) {
            $record->subjects()->get()->each->delete();
            $record->delete();
        });
    }

    /**
     * The class a student resolves to for this year.
     *
     * class_student has no effective dating, so this is explicitly the CURRENT
     * resolved context rather than history. Ambiguity is refused rather than
     * guessed -- the same rule StudentGradeResolver applies.
     */
    public function resolveClass(Student $student, $year): ?SchoolClass
    {
        $classes = $student->classes()
            ->where('academic_year_id', $year->id)
            ->wherePivot('status', 'active')
            ->with('grade')
            ->get();

        if ($classes->count() > 1) {
            $this->fail(
                'class_id',
                "{$student->fullName()} is recorded in more than one class for {$year->name} ({$classes->pluck('name')->implode(', ')}). ".
                'Correct the class membership before issuing a report card.'
            );
        }

        return $classes->first();
    }

    /**
     * The homeroom teacher's name, or null.
     *
     * Two homeroom teachers on one class is a configuration problem, not
     * something to resolve with first(): the wrong name would be printed on a
     * document and signed. Zero is fine -- the document prints an unnamed
     * signing line rather than inventing one.
     */
    public function resolveHomeroomTeacherName(?SchoolClass $class): ?string
    {
        if (! $class) {
            return null;
        }

        $staffIds = DB::table('class_teacher')
            ->where('class_id', $class->id)
            ->where('role', 'homeroom')
            ->pluck('staff_id');

        if ($staffIds->count() > 1) {
            $this->fail(
                'homeroom_teacher_name_snapshot',
                "{$class->name} has more than one homeroom teacher recorded. Resolve that before issuing a report card."
            );
        }

        return $staffIds->isEmpty() ? null : Staff::find($staffIds->first())?->fullName();
    }

    private function assertDraft(AcademicRecord $record): void
    {
        if (! $record->isDraft()) {
            $this->fail('status', 'An issued academic record is immutable. Create a correction, which will supersede it.');
        }
    }

    private function orNull(?string $value): ?string
    {
        return ($value === null || trim($value) === '') ? null : $value;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
