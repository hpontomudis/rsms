<?php

namespace App\Services;

use App\Evidence\EvidenceRegistry;
use App\Models\PerformanceFramework;
use App\Models\PerformanceIndicator;
use App\Models\PerformanceRatingOption;
use App\Models\PerformanceSection;
use App\Models\StaffCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Authoring a Performance Framework: its sections, indicators and rating
 * scale, then putting it into force.
 *
 * Structure is editable only while draft -- enforced again here even though
 * the models already guard it, because a readable service-level message
 * (naming the framework) is friendlier than a raw model exception surfacing
 * through a form.
 */
class PerformanceFrameworkService
{
    public function create(StaffCategory $category, array $attributes): PerformanceFramework
    {
        return PerformanceFramework::create([
            'staff_category_id' => $category->id,
            'name' => $attributes['name'],
            'code' => $attributes['code'],
            'version' => $attributes['version'],
            'effective_from' => $attributes['effective_from'] ?? null,
            'effective_to' => $attributes['effective_to'] ?? null,
            'status' => 'draft',
        ]);
    }

    public function addSection(PerformanceFramework $framework, array $attributes): PerformanceSection
    {
        $this->assertEditable($framework);

        return PerformanceSection::create([
            'performance_framework_id' => $framework->id,
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'position' => $this->nextPosition($framework->sections()),
        ]);
    }

    public function addIndicator(PerformanceSection $section, array $attributes): PerformanceIndicator
    {
        $this->assertEditable($section->framework);

        $this->assertValidType($attributes['indicator_type']);

        $key = $attributes['system_evidence_key'] ?? null;
        if ($key !== null && ! EvidenceRegistry::has($key)) {
            $this->fail('system_evidence_key', "\"{$key}\" is not a registered evidence source.");
        }

        return PerformanceIndicator::create([
            'performance_section_id' => $section->id,
            'name' => $attributes['name'],
            'description' => $attributes['description'] ?? null,
            'indicator_type' => $attributes['indicator_type'],
            'position' => $this->nextPosition($section->indicators()),
            'system_evidence_key' => $key,
            'target_value' => $attributes['target_value'] ?? null,
            'unit_label' => $attributes['unit_label'] ?? null,
        ]);
    }

    public function addRatingOption(PerformanceFramework $framework, array $attributes): PerformanceRatingOption
    {
        $this->assertEditable($framework);

        if ($framework->ratingOptions()->where('value', $attributes['value'])->exists()) {
            $this->fail('value', "This framework already has an option with value {$attributes['value']}.");
        }

        return PerformanceRatingOption::create([
            'performance_framework_id' => $framework->id,
            'value' => $attributes['value'],
            'label' => $attributes['label'],
            'description' => $attributes['description'] ?? null,
            'position' => $this->nextPosition($framework->ratingOptions()),
        ]);
    }

    public function removeSection(PerformanceSection $section): void
    {
        $this->assertEditable($section->framework);

        DB::transaction(function () use ($section) {
            $section->indicators()->get()->each->delete();
            $section->delete();
        });
    }

    public function removeIndicator(PerformanceIndicator $indicator): void
    {
        $this->assertEditable($indicator->framework);

        $indicator->delete();
    }

    public function removeRatingOption(PerformanceRatingOption $option): void
    {
        $this->assertEditable($option->framework);

        $option->delete();
    }

    /**
     * Put the framework into force. Requires at least one section, one
     * indicator per section, and -- since every V7A framework carries a
     * rating scale -- at least one rating option.
     */
    public function activate(PerformanceFramework $framework): PerformanceFramework
    {
        if (! $framework->isDraft()) {
            $this->fail('status', 'Only a draft framework can be activated.');
        }

        $sections = $framework->sections()->with('indicators')->get();

        if ($sections->isEmpty()) {
            $this->fail('status', 'Add at least one section before activating this framework.');
        }

        foreach ($sections as $section) {
            if ($section->indicators->isEmpty()) {
                $this->fail('status', "\"{$section->name}\" has no indicators yet.");
            }
        }

        if ($framework->ratingOptions()->count() === 0) {
            $this->fail('status', 'Add at least one rating option before activating this framework.');
        }

        $framework->update(['status' => 'active']);

        return $framework->refresh();
    }

    /** New evaluations stop; anything already in flight is unaffected. */
    public function archive(PerformanceFramework $framework): PerformanceFramework
    {
        if (! $framework->isActive()) {
            $this->fail('status', 'Only an active framework can be archived.');
        }

        $framework->update(['status' => 'archived']);

        return $framework->refresh();
    }

    public function delete(PerformanceFramework $framework): void
    {
        if (! $framework->isDraft()) {
            $this->fail('status', 'Only an unused draft framework can be deleted. Archive an active one instead.');
        }

        DB::transaction(function () use ($framework) {
            foreach ($framework->sections()->get() as $section) {
                $section->indicators()->get()->each->delete();
                $section->delete();
            }
            $framework->ratingOptions()->get()->each->delete();
            $framework->delete();
        });
    }

    private function nextPosition($relation): int
    {
        return ((int) $relation->max('position')) + 1;
    }

    private function assertValidType(string $type): void
    {
        if (! in_array($type, PerformanceIndicator::TYPES, true)) {
            $this->fail('indicator_type', "\"{$type}\" is not a recognised indicator type.");
        }
    }

    private function assertEditable(?PerformanceFramework $framework): void
    {
        if (! $framework || ! $framework->isStructureEditable()) {
            $this->fail('status', 'This framework is no longer draft, so its structure is frozen.');
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
