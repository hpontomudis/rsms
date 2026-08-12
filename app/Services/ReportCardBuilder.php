<?php

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentResult;
use App\Models\ClassSubject;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Builds one student's report card for one academic year.
 *
 * Extracted from the Livewire component in Step 2d, because discovery now has
 * to answer a harder question than "which classes is this student in". A
 * subject reaches the report card through EITHER of two paths, and the union
 * is what makes historical reporting survive a student moving between groups.
 */
class ReportCardBuilder
{
    /**
     * @return array{periods: Collection, rows: Collection, overallAverage: int|null}
     */
    public function build(Student $student, AcademicYear $year): array
    {
        // Columns come from the database, ordered by sequence. However many
        // periods a year defines -- two, three, more -- is a data question,
        // never a code one.
        $periods = $year->periods;
        $periodIds = $periods->pluck('id');

        $assignmentIds = $this->discoverAssignmentIds($student, $year, $periodIds);

        // Grouped by subject, NOT by assignment. A subject whose teacher
        // changed mid-year, or which the student took in Green A and then in
        // Blue A, has several class_subject rows and must still appear once
        // with every assessment merged in.
        $rows = ClassSubject::whereIn('id', $assignmentIds)
            ->with('subject')
            ->orderBy('id')
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($assignments) => $this->subjectRow($student, $assignments, $periods, $periodIds))
            ->values();

        $withOverall = $rows->pluck('overall')->filter(fn ($v) => $v !== null);

        return [
            'periods' => $periods,
            'rows' => $rows,
            'overallAverage' => $withOverall->isNotEmpty() ? round($withOverall->avg()) : null,
        ];
    }

    /**
     * ONE PERIOD's results -- the unit Rahai actually issues.
     *
     * Deliberately a SECOND method rather than a parameter on build(), so the
     * year formulas above cannot be changed by accident. The two differ in one
     * place only, and it matters:
     *
     *   year card overall   = round(mean of subject overalls, where a subject
     *                         overall is a flat mean over ALL its year results)
     *   period card overall = round(mean of subject PERIOD averages)
     *
     * The per-subject figure is the existing period average, unchanged. Nothing
     * here redefines how a score becomes a percentage.
     *
     * @return array{period: AcademicPeriod, rows: Collection, overallAverage: int|null}
     */
    public function buildForPeriod(Student $student, AcademicPeriod $period): array
    {
        $periodIds = collect([$period->id]);
        $year = $period->academicYear;

        $assignmentIds = $this->resultDriven($student, $periodIds)
            // Class participation carries the same known limitation as the
            // year build: class_student is not effective-dated, so the only
            // honest filter is the academic year of the class itself. A period
            // is never narrowed from enrolled_at, because the source cannot
            // prove where a student sat in September.
            ->merge($this->classParticipation($student, $year))
            ->merge($this->groupParticipationInPeriod($student, $period))
            ->unique()
            ->values();

        // Grouped by subject, exactly as the year build does, so a handover or
        // a Green A -> Blue A move still yields one row.
        $rows = ClassSubject::whereIn('id', $assignmentIds)
            ->with('subject')
            ->orderBy('id')
            ->get()
            ->groupBy('subject_id')
            ->map(function ($assignments) use ($student, $period, $periodIds) {
                $row = $this->subjectRow($student, $assignments, collect([$period]), $periodIds);

                $score = $row->periodAverages[$period->id];

                return (object) [
                    'subject' => $row->subject,
                    // Cast for the caller's convenience only. round() returns a
                    // float in the year build too; nothing about the arithmetic
                    // changes here.
                    'score' => $score === null ? null : (int) $score,
                ];
            })
            ->values()
            ->sortBy(fn ($row) => $row->subject->name)
            ->values();

        $scored = $rows->pluck('score')->filter(fn ($v) => $v !== null);

        return [
            'period' => $period,
            'rows' => $rows,
            'overallAverage' => $scored->isNotEmpty() ? (int) round($scored->avg()) : null,
        ];
    }

    /**
     * Group membership overlapping THIS PERIOD's dates rather than the whole
     * year -- the one discovery rule that genuinely narrows, because
     * teaching_group_student really is effective-dated.
     */
    private function groupParticipationInPeriod(Student $student, AcademicPeriod $period): Collection
    {
        $groupIds = $student->teachingGroupMemberships()
            ->whereHas('teachingGroup', fn ($q) => $q->where('academic_year_id', $period->academic_year_id))
            ->whereDate('started_on', '<=', $period->end_date)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $period->start_date))
            ->pluck('teaching_group_id');

        return ClassSubject::whereIn('teaching_group_id', $groupIds)->pluck('id');
    }

    /**
     * The two discovery paths, unioned and deduplicated.
     *
     * Deduplication matters: an assignment the student both holds a result on
     * AND currently participates in is found by both paths. Because the union
     * is over assignment IDs -- and each assessment belongs to exactly one
     * assignment -- no result can be counted twice downstream.
     */
    private function discoverAssignmentIds(Student $student, AcademicYear $year, Collection $periodIds): Collection
    {
        return $this->resultDriven($student, $periodIds)
            ->merge($this->classParticipation($student, $year))
            ->merge($this->groupParticipation($student, $year))
            ->unique()
            ->values();
    }

    /**
     * RESULT-DRIVEN: anything this student was actually scored on.
     *
     * Authoritative for history, and deliberately unfiltered by membership,
     * assignment state or group state. A recorded mark must stay reportable
     * after the student leaves the group, after the assignment closes, after
     * the group is archived, and after the teacher changes -- archived means
     * no new activity, not that the past stops having happened.
     */
    private function resultDriven(Student $student, Collection $periodIds): Collection
    {
        return Assessment::whereIn('academic_period_id', $periodIds)
            ->whereHas('results', fn ($q) => $q->where('student_id', $student->id))
            ->pluck('class_subject_id');
    }

    /**
     * PARTICIPATION-DRIVEN (class): unchanged from before Step 2d, including
     * its known limitation. class_student has no effective dating, so the only
     * available filter is the academic year of the class itself -- no
     * chronology is invented from enrolled_at.
     */
    private function classParticipation(Student $student, AcademicYear $year): Collection
    {
        $classIds = $student->classes()
            ->where('academic_year_id', $year->id)
            ->pluck('classes.id');

        return ClassSubject::whereIn('class_id', $classIds)->pluck('id');
    }

    /**
     * PARTICIPATION-DRIVEN (teaching group): membership whose validity range
     * OVERLAPS the academic year, not merely membership still open today.
     * A Green A membership that closed in December still counts towards the
     * annual report.
     *
     * Discovery is through this student's own membership rows only. It never
     * goes via grade or programme, so a Green A student cannot pick up Blue A's
     * subjects just because both teach English to Year 5.
     */
    private function groupParticipation(Student $student, AcademicYear $year): Collection
    {
        $groupIds = $student->teachingGroupMemberships()
            ->whereHas('teachingGroup', fn ($q) => $q->where('academic_year_id', $year->id))
            ->whereDate('started_on', '<=', $year->end_date)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $year->start_date))
            ->pluck('teaching_group_id');

        return ClassSubject::whereIn('teaching_group_id', $groupIds)->pluck('id');
    }

    /**
     * One subject row. The arithmetic is deliberately unchanged from Phase 4:
     *
     *   percentage      = score / assessment.max_score * 100
     *   period average  = round(mean of percentages whose assessment sits in
     *                     that period)
     *   subject overall = round(mean of percentages of ALL that subject's
     *                     results) -- a flat mean over results, NOT a mean of
     *                     the period averages
     *
     * Step 2d widens discovery only; it does not redefine grading.
     */
    private function subjectRow(Student $student, Collection $assignments, Collection $periods, Collection $periodIds): object
    {
        $assignmentIds = $assignments->pluck('id');

        // Results are constrained to the requested year's periods, so an
        // assessment belonging to another year can never leak in.
        $results = AssessmentResult::where('student_id', $student->id)
            ->whereHas('assessment', fn ($q) => $q
                ->whereIn('class_subject_id', $assignmentIds)
                ->whereIn('academic_period_id', $periodIds))
            ->with('assessment')
            ->get();

        $percentages = fn ($group) => $group->map(
            fn (AssessmentResult $r) => (float) $r->score / (float) $r->assessment->max_score * 100
        );

        $periodAverages = $periods->mapWithKeys(function ($period) use ($results, $percentages) {
            $inPeriod = $results->filter(
                fn (AssessmentResult $r) => $r->assessment->academic_period_id === $period->id
            );

            return [$period->id => $inPeriod->isNotEmpty() ? round($percentages($inPeriod)->avg()) : null];
        });

        return (object) [
            'subject' => $assignments->first()->subject,
            'periodAverages' => $periodAverages,
            'overall' => $results->isNotEmpty() ? round($percentages($results)->avg()) : null,
        ];
    }
}
