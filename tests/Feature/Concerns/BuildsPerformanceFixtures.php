<?php

namespace Tests\Feature\Concerns;

use App\Models\AuditLog;
use App\Models\PerformanceEvaluation;
use App\Models\PerformanceFramework;
use App\Models\StaffCategory;
use App\Models\User;
use App\Services\PerformanceFrameworkService;
use Database\Seeders\StaffCategorySeeder;

/**
 * A staff category and a fully activated Framework (one section, one rubric
 * indicator wired to a real evidence key, one numeric indicator with no
 * evidence, two rating options) -- the minimum shape an Evaluation can be
 * created against.
 */
trait BuildsPerformanceFixtures
{
    protected function seedPerformanceReferenceData(): void
    {
        $this->seed(StaffCategorySeeder::class);
    }

    protected function teacherCategory(): StaffCategory
    {
        return StaffCategory::where('code', 'teacher')->firstOrFail();
    }

    protected function staffInCategory(StaffCategory $category, ?int $userId = null): \App\Models\Staff
    {
        return \App\Models\Staff::create([
            'staff_number' => 'S-'.uniqid(), 'first_name' => 'Test', 'last_name' => 'Staff',
            'staff_category_id' => $category->id, 'position_id' => \App\Models\Position::firstOrFail()->id,
            'phone' => '08', 'hire_date' => '2020-01-01', 'status' => 'active', 'user_id' => $userId,
        ]);
    }

    /**
     * @return array{0: PerformanceFramework, 1: \App\Models\PerformanceIndicator, 2: \App\Models\PerformanceIndicator, 3: \App\Models\PerformanceRatingOption, 4: \App\Models\PerformanceRatingOption}
     */
    protected function activeFramework(?StaffCategory $category = null, string $code = 'FW', string $version = '1'): array
    {
        $category ??= $this->teacherCategory();
        $frameworks = app(PerformanceFrameworkService::class);

        $framework = $frameworks->create($category, ['name' => 'Test Framework', 'code' => $code, 'version' => $version]);
        $section = $frameworks->addSection($framework, ['name' => 'Planning']);
        $rubric = $frameworks->addIndicator($section, [
            'name' => 'Maintains Prota', 'indicator_type' => 'rubric', 'system_evidence_key' => 'annual_programme_context',
        ]);
        $numeric = $frameworks->addIndicator($section, [
            'name' => 'Modules written', 'indicator_type' => 'numeric', 'system_evidence_key' => 'teaching_module_count',
        ]);
        $high = $frameworks->addRatingOption($framework, ['value' => 4, 'label' => 'Highly Effective']);
        $low = $frameworks->addRatingOption($framework, ['value' => 1, 'label' => 'Needs Improvement']);

        $framework = $frameworks->activate($framework->fresh());

        return [$framework, $rubric->fresh(), $numeric->fresh(), $high->fresh(), $low->fresh()];
    }

    protected function userWithRole(string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $role.'@rahai.test'],
            ['name' => ucfirst($role), 'password' => bcrypt('password'), 'status' => 'active'],
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }

    protected function auditCount(string $model, string $action): int
    {
        return AuditLog::where('auditable_type', $model)->where('action', $action)->count();
    }

    protected function respondToEveryItem(PerformanceEvaluation $evaluation, int $ratingOptionId): void
    {
        $items = app(\App\Services\PerformanceEvaluationItemService::class);

        foreach ($evaluation->items()->with('indicator')->get() as $item) {
            $payload = match ($item->indicator->indicator_type) {
                'rubric' => ['rating_option_id' => $ratingOptionId],
                'numeric' => ['numeric_value' => 5],
                'boolean' => ['boolean_value' => true],
                'narrative' => ['narrative_response' => 'Solid.'],
            };
            $items->respond($item, $payload);
        }
    }
}
