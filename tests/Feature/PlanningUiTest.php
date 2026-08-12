<?php

namespace Tests\Feature;

use App\Livewire\Planning\AnnualProgrammeShow;
use App\Livewire\Planning\Create;
use App\Livewire\Planning\Index;
use App\Livewire\Planning\SemesterProgrammeShow;
use App\Livewire\Teaching\MyAssignments;
use App\Models\AnnualProgramme;
use App\Models\ClassSubject;
use App\Models\SemesterProgramme;
use App\Services\SemesterProgrammeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\BuildsPlanningFixtures;
use Tests\TestCase;

/**
 * The planning screens: Prota, Prosem, and their entry points.
 *
 * These test what a teacher can actually reach and do, which is where the
 * roster anchoring becomes visible -- a successor opens the same plan.
 */
class PlanningUiTest extends TestCase
{
    use BuildsPlanningFixtures;
    use RefreshDatabase;

    // ------------------------------------------------------------ annual

    public function test_the_annual_programme_groups_its_items_by_period(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 1'), 8);
        $this->programmes()->addItem($programme, $this->pathwayItem(2), $this->period('Semester 2'), 6);

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(AnnualProgrammeShow::class, ['annualProgramme' => $programme])
            ->assertSee('Semester 1')
            ->assertSee('Semester 2')
            ->assertSee('TP 1')
            ->assertSee('TP 2')
            ->assertSee('8 JP');
    }

    public function test_an_assigned_teacher_can_allocate_an_objective_from_the_screen(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $teacher = $this->teacherFor('Year 5A', 'Maths', 'sarah');

        Livewire::actingAs($teacher)
            ->test(AnnualProgrammeShow::class, ['annualProgramme' => $programme])
            ->set('showAddItem', true)
            ->set('learning_pathway_item_id', (string) $this->pathwayItem(1)->id)
            ->set('academic_period_id', (string) $this->period('Semester 1')->id)
            ->set('planned_lesson_periods', '10')
            ->call('addItem')
            ->assertHasNoErrors();

        $this->assertSame(1, $programme->fresh()->items()->count());
        $this->assertSame(10, $programme->fresh()->items()->first()->planned_lesson_periods);
    }

    public function test_an_unassigned_teacher_sees_the_plan_but_gets_no_controls(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $outsider = $this->staff('rina')->user->fresh();

        Livewire::actingAs($outsider)
            ->test(AnnualProgrammeShow::class, ['annualProgramme' => $programme])
            ->assertSet('showAddItem', false)
            ->assertViewHas('canEdit', false)
            ->assertViewHas('canTransition', false);
    }

    public function test_a_teacher_cannot_activate_even_their_own_plan(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $this->programmes()->addItem($programme, $this->pathwayItem(1), $this->period('Semester 1'), 8);
        $teacher = $this->teacherFor('Year 5A', 'Maths', 'sarah');

        Livewire::actingAs($teacher)
            ->test(AnnualProgrammeShow::class, ['annualProgramme' => $programme])
            ->call('activate')
            ->assertForbidden();

        $this->assertTrue($programme->fresh()->isDraft());
    }

    public function test_activation_failure_is_reported_rather_than_thrown(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(AnnualProgrammeShow::class, ['annualProgramme' => $programme])
            ->call('activate')
            ->assertHasErrors('items');

        $this->assertTrue($programme->fresh()->isDraft());
    }

    public function test_planning_a_period_creates_the_semester_programme_and_redirects(): void
    {
        $this->seedReferenceData();
        $programme = $this->activatedClassProgramme();

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(AnnualProgrammeShow::class, ['annualProgramme' => $programme])
            ->call('planPeriod', $this->period('Semester 1')->id)
            ->assertRedirect();

        $semester = SemesterProgramme::where('annual_programme_id', $programme->id)->firstOrFail();
        $this->assertSame($this->period('Semester 1')->id, $semester->academic_period_id);
    }

    public function test_planning_the_same_period_twice_reuses_the_existing_programme(): void
    {
        $this->seedReferenceData();
        [$annual] = $this->scheduledProgramme();

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(AnnualProgrammeShow::class, ['annualProgramme' => $annual])
            ->call('planPeriod', $this->period('Semester 1')->id);

        $this->assertSame(1, SemesterProgramme::where('annual_programme_id', $annual->id)->count());
    }

    // ---------------------------------------------------------- semester

    public function test_the_semester_screen_shows_several_slots_for_one_objective(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->scheduledProgramme();
        $item = $annual->items()->first();

        app(SemesterProgrammeService::class)->addSlot($semester, $item, ['week_label' => 'Week 4']);

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(SemesterProgrammeShow::class, ['semesterProgramme' => $semester])
            ->assertSee('Week 1')
            ->assertSee('Week 4')
            ->assertSee('2 slots');
    }

    public function test_the_jp_summary_reports_a_shortfall_before_activation_is_attempted(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->scheduledProgramme();

        // The budget is 8 JP; re-scheduling the single slot to 5 leaves a gap.
        app(SemesterProgrammeService::class)->updateSlot($semester->items()->first(), ['planned_lesson_periods' => 5]);

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(SemesterProgrammeShow::class, ['semesterProgramme' => $semester->fresh()])
            ->assertSee('5/8 JP');
    }

    public function test_activation_refuses_a_jp_mismatch_from_the_screen(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->scheduledProgramme();

        app(SemesterProgrammeService::class)->updateSlot($semester->items()->first(), ['planned_lesson_periods' => 5]);

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(SemesterProgrammeShow::class, ['semesterProgramme' => $semester->fresh()])
            ->call('activate')
            ->assertHasErrors('items');

        $this->assertTrue($semester->fresh()->isDraft());
    }

    public function test_a_teacher_can_add_and_reorder_slots(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->scheduledProgramme();
        $teacher = $this->teacherFor('Year 5A', 'Maths', 'sarah');

        Livewire::actingAs($teacher)
            ->test(SemesterProgrammeShow::class, ['semesterProgramme' => $semester])
            ->set('showAddSlot', true)
            ->set('annual_programme_item_id', (string) $annual->items()->first()->id)
            ->set('week_label', 'Week 6')
            ->call('addSlot')
            ->assertHasNoErrors();

        $second = $semester->fresh()->items()->where('week_label', 'Week 6')->firstOrFail();
        $this->assertSame(2, $second->position);

        Livewire::actingAs($teacher)
            ->test(SemesterProgrammeShow::class, ['semesterProgramme' => $semester->fresh()])
            ->call('moveSlot', $second->id, 'up');

        $this->assertSame(1, $second->fresh()->position);
    }

    // ------------------------------------------------------------- entry

    public function test_the_index_lists_programmes_for_the_chosen_year(): void
    {
        $this->seedReferenceData();
        $this->classProgramme();

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(Index::class)
            ->assertSee('Year 5A')
            ->assertSee('Maths')
            ->set('status', 'active')
            ->assertDontSee('Phase C route');
    }

    public function test_creation_offers_only_pathways_the_roster_can_follow(): void
    {
        $this->seedReferenceData();
        $class = $this->class('Year 5', 'Year 5A');
        $this->pathway();                    // Phase C, Maths -- eligible
        $this->pathwayFor('D', 'Maths', 'Phase D route'); // wrong phase

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(Create::class)
            ->set('roster_type', 'class')
            ->set('class_id', (string) $class->id)
            ->set('subject_id', (string) $this->subject('Maths')->id)
            ->assertSee('Phase C route')
            ->assertDontSee('Phase D route');
    }

    public function test_creation_from_the_screen_produces_a_draft(): void
    {
        $this->seedReferenceData();
        $class = $this->class('Year 5', 'Year 5A');
        $pathway = $this->pathway();

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(Create::class)
            ->set('roster_type', 'class')
            ->set('class_id', (string) $class->id)
            ->set('subject_id', (string) $this->subject('Maths')->id)
            ->set('learning_pathway_id', (string) $pathway->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $programme = AnnualProgramme::firstOrFail();
        $this->assertTrue($programme->isDraft());
        $this->assertSame($class->id, $programme->class_id);
    }

    public function test_creation_refuses_a_pathway_the_roster_cannot_follow(): void
    {
        $this->seedReferenceData();
        $class = $this->class('Year 5', 'Year 5A');
        $wrong = $this->pathwayFor('D', 'Maths', 'Phase D route');

        Livewire::actingAs($this->userWithRole('principal'))
            ->test(Create::class)
            ->set('roster_type', 'class')
            ->set('class_id', (string) $class->id)
            ->set('subject_id', (string) $this->subject('Maths')->id)
            ->set('learning_pathway_id', (string) $wrong->id)
            ->call('save')
            ->assertHasErrors('learning_pathway_id');

        $this->assertSame(0, AnnualProgramme::count());
    }

    // -------------------------------------------------- teacher workspace

    public function test_my_teaching_links_to_the_annual_programme(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();
        $teacher = $this->teacherFor('Year 5A', 'Maths', 'sarah');

        Livewire::actingAs($teacher)
            ->test(MyAssignments::class)
            ->assertSee('Annual Programme')
            ->assertSee(route('planning.annual.show', $programme), false);
    }

    public function test_my_teaching_offers_to_start_a_plan_when_none_exists(): void
    {
        $this->seedReferenceData();
        $this->class('Year 5', 'Year 5A');
        $teacher = $this->teacherFor('Year 5A', 'Maths', 'sarah');

        Livewire::actingAs($teacher)
            ->test(MyAssignments::class)
            ->assertSee('Start an annual programme');
    }

    /** The succession case the whole anchoring exists for. */
    public function test_a_successor_sees_the_predecessors_plan_on_her_own_card(): void
    {
        $this->seedReferenceData();
        $programme = $this->classProgramme();

        $sarah = $this->teacherFor('Year 5A', 'Maths', 'sarah');
        ClassSubject::where('staff_id', $sarah->staff->id)->update(['ended_on' => '2026-11-30']);
        $eka = $this->teacherFor('Year 5A', 'Maths', 'eka', '2026-12-01');

        Livewire::actingAs($eka)
            ->test(MyAssignments::class)
            ->assertSee(route('planning.annual.show', $programme), false);

        // And Sarah still reads it under her closed assignment.
        Livewire::actingAs($sarah->fresh())
            ->test(MyAssignments::class)
            ->assertSee(route('planning.annual.show', $programme), false);

        $this->assertTrue($eka->can('update', $programme));
        $this->assertFalse($sarah->fresh()->can('update', $programme));
    }

    public function test_the_current_semester_programme_is_surfaced_on_the_card(): void
    {
        $this->seedReferenceData();
        [$annual, $semester] = $this->scheduledProgramme();
        $teacher = $this->teacherFor('Year 5A', 'Maths', 'sarah');

        // Semester 1 runs 2026-07-01 to 2026-12-31.
        $this->travelTo('2026-09-15');

        Livewire::actingAs($teacher)
            ->test(MyAssignments::class)
            ->assertSee(route('planning.semester.show', $semester), false);

        $this->travelTo('2027-03-01');

        Livewire::actingAs($teacher)
            ->test(MyAssignments::class)
            ->assertDontSee(route('planning.semester.show', $semester), false);
    }
}

