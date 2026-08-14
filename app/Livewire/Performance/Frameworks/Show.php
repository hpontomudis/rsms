<?php

namespace App\Livewire\Performance\Frameworks;

use App\Evidence\EvidenceRegistry;
use App\Models\PerformanceFramework;
use App\Models\PerformanceIndicator;
use App\Models\PerformanceSection;
use App\Services\PerformanceFrameworkService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public PerformanceFramework $framework;

    // ------------------------------------------------------------ sections
    public bool $showAddSection = false;

    public string $section_name = '';

    public string $section_description = '';

    // ----------------------------------------------------------- indicators
    public ?int $addIndicatorSectionId = null;

    public string $indicator_name = '';

    public string $indicator_description = '';

    public string $indicator_type = '';

    public string $indicator_system_evidence_key = '';

    public string $indicator_target_value = '';

    public string $indicator_unit_label = '';

    // -------------------------------------------------------- rating options
    public bool $showAddRatingOption = false;

    public string $rating_value = '';

    public string $rating_label = '';

    public string $rating_description = '';

    public function mount(PerformanceFramework $framework): void
    {
        $this->authorize('view', $framework);
        $this->framework = $framework;
    }

    // ------------------------------------------------------------ sections

    public function addSection(PerformanceFrameworkService $frameworks): void
    {
        $this->authorize('update', $this->framework);

        $validated = $this->validate([
            'section_name' => ['required', 'string', 'max:150'],
            'section_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $frameworks->addSection($this->framework, [
            'name' => $validated['section_name'],
            'description' => $validated['section_description'] !== '' ? $validated['section_description'] : null,
        ]);

        $this->reset(['showAddSection', 'section_name', 'section_description']);
        $this->framework->refresh();
    }

    public function removeSection(int $sectionId, PerformanceFrameworkService $frameworks): void
    {
        $this->authorize('update', $this->framework);

        $frameworks->removeSection($this->framework->sections()->findOrFail($sectionId));

        $this->framework->refresh();
    }

    // ----------------------------------------------------------- indicators

    public function startAddIndicator(int $sectionId): void
    {
        $this->authorize('update', $this->framework);

        $this->reset(['indicator_name', 'indicator_description', 'indicator_type', 'indicator_system_evidence_key', 'indicator_target_value', 'indicator_unit_label']);
        $this->addIndicatorSectionId = $sectionId;
        $this->resetErrorBag();
    }

    public function cancelAddIndicator(): void
    {
        $this->reset(['addIndicatorSectionId', 'indicator_name', 'indicator_description', 'indicator_type', 'indicator_system_evidence_key', 'indicator_target_value', 'indicator_unit_label']);
    }

    public function addIndicator(PerformanceFrameworkService $frameworks): void
    {
        $this->authorize('update', $this->framework);

        $validated = $this->validate([
            'indicator_name' => ['required', 'string', 'max:150'],
            'indicator_description' => ['nullable', 'string', 'max:1000'],
            'indicator_type' => ['required', 'in:'.implode(',', PerformanceIndicator::TYPES)],
            'indicator_system_evidence_key' => ['nullable', 'in:'.implode(',', EvidenceRegistry::KEYS)],
            'indicator_target_value' => ['nullable', 'numeric'],
            'indicator_unit_label' => ['nullable', 'string', 'max:50'],
        ]);

        $section = $this->framework->sections()->findOrFail($this->addIndicatorSectionId);

        $frameworks->addIndicator($section, [
            'name' => $validated['indicator_name'],
            'description' => $validated['indicator_description'] !== '' ? $validated['indicator_description'] : null,
            'indicator_type' => $validated['indicator_type'],
            'system_evidence_key' => $validated['indicator_system_evidence_key'] !== '' ? $validated['indicator_system_evidence_key'] : null,
            'target_value' => $validated['indicator_target_value'] !== '' ? $validated['indicator_target_value'] : null,
            'unit_label' => $validated['indicator_unit_label'] !== '' ? $validated['indicator_unit_label'] : null,
        ]);

        $this->cancelAddIndicator();
        $this->framework->refresh();
    }

    public function removeIndicator(int $indicatorId, PerformanceFrameworkService $frameworks): void
    {
        $this->authorize('update', $this->framework);

        $frameworks->removeIndicator(PerformanceIndicator::whereHas(
            'section', fn ($q) => $q->where('performance_framework_id', $this->framework->id)
        )->findOrFail($indicatorId));

        $this->framework->refresh();
    }

    // -------------------------------------------------------- rating options

    public function addRatingOption(PerformanceFrameworkService $frameworks): void
    {
        $this->authorize('update', $this->framework);

        $validated = $this->validate([
            'rating_value' => ['required', 'integer'],
            'rating_label' => ['required', 'string', 'max:100'],
            'rating_description' => ['nullable', 'string', 'max:500'],
        ]);

        $frameworks->addRatingOption($this->framework, [
            'value' => $validated['rating_value'],
            'label' => $validated['rating_label'],
            'description' => $validated['rating_description'] !== '' ? $validated['rating_description'] : null,
        ]);

        $this->reset(['showAddRatingOption', 'rating_value', 'rating_label', 'rating_description']);
        $this->framework->refresh();
    }

    public function removeRatingOption(int $optionId, PerformanceFrameworkService $frameworks): void
    {
        $this->authorize('update', $this->framework);

        $frameworks->removeRatingOption($this->framework->ratingOptions()->findOrFail($optionId));

        $this->framework->refresh();
    }

    // -------------------------------------------------------------- lifecycle

    public function activate(PerformanceFrameworkService $frameworks): void
    {
        $this->authorize('activate', $this->framework);

        $frameworks->activate($this->framework);

        $this->framework->refresh();
    }

    public function archive(PerformanceFrameworkService $frameworks): void
    {
        $this->authorize('archive', $this->framework);

        $frameworks->archive($this->framework);

        $this->framework->refresh();
    }

    public function delete(PerformanceFrameworkService $frameworks)
    {
        $this->authorize('delete', $this->framework);

        $frameworks->delete($this->framework);

        session()->flash('status', 'Draft framework deleted.');

        return $this->redirect(route('performance.frameworks.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.performance.frameworks.show', [
            'sections' => $this->framework->sections()->with('indicators')->get(),
            'ratingOptions' => $this->framework->ratingOptions()->get(),
            'evidenceDescriptions' => EvidenceRegistry::DESCRIPTIONS,
        ]);
    }
}
