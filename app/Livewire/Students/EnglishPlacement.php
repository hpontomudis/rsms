<?php

namespace App\Livewire\Students;

use App\Models\EnglishLevel;
use App\Models\Student;
use App\Models\StudentEnglishLevelPlacement;
use App\Services\EnglishPlacementService;
use App\Services\StudentGradeResolver;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A student's assessed English proficiency.
 *
 * Gated on the placement policy (`academics.manage`), not StudentPolicy --
 * this screen is academic administration, and Step 2a-ii deliberately does not
 * widen who may see student rosters.
 */
#[Layout('layouts.app')]
class EnglishPlacement extends Component
{
    public Student $student;

    public bool $showChangeLevel = false;

    public string $english_level_id = '';

    public string $started_on = '';

    public string $assessed_on = '';

    public string $placement_reason = '';

    public string $notes = '';

    public function mount(Student $student): void
    {
        $this->authorize('viewAny', StudentEnglishLevelPlacement::class);
        $this->student = $student;
        $this->started_on = Carbon::today()->toDateString();
    }

    public function placeLevel(EnglishPlacementService $placements): void
    {
        $this->authorize('create', StudentEnglishLevelPlacement::class);

        $validated = $this->validate([
            'english_level_id' => ['required', 'exists:english_levels,id'],
            'started_on' => ['required', 'date'],
            'assessed_on' => ['nullable', 'date'],
            'placement_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Close-and-open lives in the service, along with the eligibility rule
        // that the level must belong to the programme covering the student's
        // grade. Changing proficiency deliberately does not touch group
        // membership.
        $placements->place(
            $this->student,
            EnglishLevel::findOrFail($validated['english_level_id']),
            Carbon::parse($validated['started_on']),
            $validated['assessed_on'] !== '' ? Carbon::parse($validated['assessed_on']) : null,
            $validated['placement_reason'] !== '' ? $validated['placement_reason'] : null,
            $validated['notes'] !== '' ? $validated['notes'] : null,
        );

        $this->reset(['english_level_id', 'assessed_on', 'placement_reason', 'notes', 'showChangeLevel']);
        $this->started_on = Carbon::today()->toDateString();
    }

    public function render(EnglishPlacementService $placements, StudentGradeResolver $grades)
    {
        $grade = $grades->gradeOn($this->student, Carbon::today(), $reason);

        return view('livewire.students.english-placement', [
            'current' => $placements->current($this->student),
            'history' => $this->student->englishPlacements()->with('englishLevel.programme')->get(),
            'eligibleLevels' => $placements->eligibleLevels($this->student),
            'grade' => $grade,
            'gradeProblem' => $grade ? null : ($reason === StudentGradeResolver::AMBIGUOUS
                ? 'This student has active classes in more than one grade, so their English programme cannot be determined.'
                : 'This student has no active class in the current academic year, so their English programme cannot be determined.'),
            'programme' => $grade?->englishProgramme(),
            'activeGroups' => $this->student->teachingGroupMemberships()
                ->whereNull('ended_on')
                ->with('teachingGroup.englishLevel')
                ->get(),
        ]);
    }
}
