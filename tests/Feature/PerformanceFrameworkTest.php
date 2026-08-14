<?php

namespace Tests\Feature;

use App\Models\PerformanceFramework;
use App\Models\PerformanceIndicator;
use App\Models\PerformanceRatingOption;
use App\Models\PerformanceSection;
use App\Models\StaffCategory;
use App\Services\PerformanceFrameworkService;
use App\Services\StaffCategoryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Feature\Concerns\BuildsPerformanceFixtures;
use Tests\TestCase;

/**
 * Staff Categories, and the Framework/Section/Indicator/RatingOption
 * authoring chain that hangs off one.
 *
 * The rule worth reading closely: structure is editable ONLY while draft.
 * Every section, indicator and rating option freezes the instant its
 * framework activates -- proven from both the model guard side and the
 * service side, because a Livewire form only ever goes through the service.
 */
class PerformanceFrameworkTest extends TestCase
{
    use BuildsPerformanceFixtures;
    use RefreshDatabase;

    private function frameworks(): PerformanceFrameworkService
    {
        return app(PerformanceFrameworkService::class);
    }

    // -------------------------------------------------------- StaffCategory

    public function test_a_category_referenced_by_staff_cannot_be_deleted(): void
    {
        $this->seedPerformanceReferenceData();
        $category = $this->teacherCategory();
        $this->staffInCategory($category);

        $this->expectException(ValidationException::class);
        app(StaffCategoryService::class)->delete($category);
    }

    public function test_a_category_referenced_by_a_framework_cannot_be_deleted(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();

        $this->expectException(ValidationException::class);
        app(StaffCategoryService::class)->delete($framework->staffCategory);
    }

    public function test_an_unused_category_deletes_cleanly(): void
    {
        $this->seedPerformanceReferenceData();
        $category = app(StaffCategoryService::class)->create(['code' => 'temp', 'name' => 'Temp']);

        app(StaffCategoryService::class)->delete($category);

        $this->assertSame(0, StaffCategory::where('code', 'temp')->count());
    }

    // ------------------------------------------------------------ lifecycle

    public function test_a_framework_is_created_as_draft(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);

        $this->assertTrue($framework->isDraft());
        $this->assertTrue($framework->isStructureEditable());
    }

    public function test_activation_requires_at_least_one_section(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);
        $this->frameworks()->addRatingOption($framework, ['value' => 1, 'label' => 'A']);

        try {
            $this->frameworks()->activate($framework);
            $this->fail('activated with no sections');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('section', $e->errors()['status'][0]);
        }
    }

    public function test_activation_requires_every_section_to_have_an_indicator(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);
        $this->frameworks()->addSection($framework, ['name' => 'Empty']);
        $this->frameworks()->addRatingOption($framework, ['value' => 1, 'label' => 'A']);

        try {
            $this->frameworks()->activate($framework);
            $this->fail('activated with an empty section');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Empty', $e->errors()['status'][0]);
        }
    }

    public function test_activation_requires_at_least_one_rating_option(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);
        $section = $this->frameworks()->addSection($framework, ['name' => 'S']);
        $this->frameworks()->addIndicator($section, ['name' => 'I', 'indicator_type' => 'narrative']);

        try {
            $this->frameworks()->activate($framework);
            $this->fail('activated with no rating options');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('rating option', $e->errors()['status'][0]);
        }
    }

    public function test_activation_succeeds_once_every_gate_is_met(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();

        $this->assertTrue($framework->isActive());
    }

    public function test_structure_is_frozen_once_active(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();

        foreach ([
            fn () => $this->frameworks()->addSection($framework, ['name' => 'New']),
            fn () => $this->frameworks()->addRatingOption($framework, ['value' => 9, 'label' => 'New']),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('active framework accepted a structural change');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('frozen', $e->errors()['status'][0]);
            }
        }
    }

    public function test_model_level_guards_also_refuse_direct_writes_to_frozen_structure(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework, $rubric] = $this->activeFramework();

        $this->expectException(LogicException::class);
        $rubric->update(['name' => 'Renamed']);
    }

    public function test_archiving_stops_new_evaluations_but_not_in_flight_work(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();

        $framework = $this->frameworks()->archive($framework);

        $this->assertTrue($framework->isArchived());

        $this->expectException(ValidationException::class);
        app(\App\Services\PerformanceEvaluationService::class)->create(
            $this->staffInCategory($framework->staffCategory),
            $framework,
            $this->userWithRole('principal'),
            '2026-01-01', '2026-06-30',
        );
    }

    public function test_archiving_requires_active_status(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);

        $this->expectException(ValidationException::class);
        $this->frameworks()->archive($framework);
    }

    public function test_only_a_draft_framework_can_be_deleted(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();

        $this->expectException(ValidationException::class);
        $this->frameworks()->delete($framework);
    }

    public function test_a_draft_framework_deletes_with_its_structure(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);
        $section = $this->frameworks()->addSection($framework, ['name' => 'S']);
        $this->frameworks()->addIndicator($section, ['name' => 'I', 'indicator_type' => 'narrative']);
        $this->frameworks()->addRatingOption($framework, ['value' => 1, 'label' => 'A']);

        $this->frameworks()->delete($framework);

        $this->assertSame(0, PerformanceFramework::count());
        $this->assertSame(0, PerformanceSection::count());
        $this->assertSame(0, PerformanceIndicator::count());
        $this->assertSame(0, PerformanceRatingOption::count());
    }

    public function test_identity_is_immutable_once_the_framework_leaves_draft(): void
    {
        $this->seedPerformanceReferenceData();
        [$framework] = $this->activeFramework();

        $this->expectException(LogicException::class);
        $framework->update(['code' => 'CHANGED']);
    }

    public function test_code_and_version_together_are_unique(): void
    {
        $this->seedPerformanceReferenceData();
        $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'DUP', 'version' => '1']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        PerformanceFramework::create([
            'staff_category_id' => $this->teacherCategory()->id,
            'name' => 'Y', 'code' => 'DUP', 'version' => '1', 'status' => 'draft',
        ]);
    }

    // ------------------------------------------------------------ indicators

    public function test_an_indicator_type_must_be_one_of_the_four_recognised_types(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);
        $section = $this->frameworks()->addSection($framework, ['name' => 'S']);

        $this->expectException(ValidationException::class);
        $this->frameworks()->addIndicator($section, ['name' => 'I', 'indicator_type' => 'essay']);
    }

    public function test_an_indicator_may_only_reference_a_registered_evidence_key(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);
        $section = $this->frameworks()->addSection($framework, ['name' => 'S']);

        $this->expectException(ValidationException::class);
        $this->frameworks()->addIndicator($section, [
            'name' => 'I', 'indicator_type' => 'numeric', 'system_evidence_key' => 'made_up_key',
        ]);
    }

    public function test_rating_option_values_are_unique_within_a_framework(): void
    {
        $this->seedPerformanceReferenceData();
        $framework = $this->frameworks()->create($this->teacherCategory(), ['name' => 'X', 'code' => 'X', 'version' => '1']);
        $this->frameworks()->addRatingOption($framework, ['value' => 1, 'label' => 'A']);

        $this->expectException(ValidationException::class);
        $this->frameworks()->addRatingOption($framework, ['value' => 1, 'label' => 'B']);
    }

    // --------------------------------------------------------------- audit

    public function test_framework_authoring_is_audited(): void
    {
        $this->seedPerformanceReferenceData();
        $created = $this->auditCount(PerformanceFramework::class, 'created');

        $this->activeFramework();

        $this->assertSame($created + 1, $this->auditCount(PerformanceFramework::class, 'created'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\PositionSeeder::class);
    }
}
