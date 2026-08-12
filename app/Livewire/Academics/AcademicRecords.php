<?php

namespace App\Livewire\Academics;

use App\Models\AcademicPeriod;
use App\Models\AcademicRecord;
use App\Models\Student;
use App\Services\AcademicRecordService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * A student's issued report cards, and the drafts on their way to becoming one.
 *
 * The draft preview deliberately renders LIVE data: a draft holds no academic
 * values, so what it shows is what publishing right now would issue.
 */
#[Layout('layouts.app')]
class AcademicRecords extends Component
{
    public Student $student;

    public string $academic_period_id = '';

    public string $homeroom_comment = '';

    public ?int $editingId = null;

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);
        abort_unless(Auth::user()->can('academics.view'), 403);

        $this->student = $student;
    }

    public function createDraft(AcademicRecordService $records): void
    {
        $this->authorize('create', AcademicRecord::class);

        $this->validate(['academic_period_id' => ['required', 'exists:academic_periods,id']]);

        $draft = $records->createDraft($this->student, AcademicPeriod::findOrFail($this->academic_period_id));

        $this->reset('academic_period_id');
        $this->editingId = $draft->id;
    }

    public function startEditing(int $id): void
    {
        $record = $this->record($id);
        $this->authorize('update', $record);

        $this->editingId = $record->id;
        $this->homeroom_comment = $record->homeroom_comment ?? '';
    }

    public function saveComment(AcademicRecordService $records): void
    {
        $record = $this->record($this->editingId);
        $this->authorize('update', $record);

        $this->validate(['homeroom_comment' => ['nullable', 'string']]);

        $records->updateDraft($record, ['homeroom_comment' => $this->homeroom_comment]);
    }

    public function publish(AcademicRecordService $records): void
    {
        $record = $this->record($this->editingId);
        $this->authorize('publish', $record);

        $records->publish($record, Auth::user());

        $this->reset(['editingId', 'homeroom_comment']);
    }

    public function deleteDraft(int $id, AcademicRecordService $records): void
    {
        $record = $this->record($id);
        $this->authorize('delete', $record);

        $records->deleteDraft($record);

        $this->reset(['editingId', 'homeroom_comment']);
    }

    private function record(?int $id): AcademicRecord
    {
        return AcademicRecord::where('student_id', $this->student->id)->findOrFail($id);
    }

    public function render(AcademicRecordService $records)
    {
        $all = AcademicRecord::where('student_id', $this->student->id)
            ->with(['academicPeriod.academicYear', 'publishedBy'])
            ->orderByDesc('id')->get();

        $draft = $this->editingId ? $all->firstWhere('id', $this->editingId) : null;

        return view('livewire.academics.academic-records', [
            'records' => $all,
            'draft' => $draft?->isDraft() ? $draft : null,
            // Live, so a draft always previews what publishing now would issue.
            'preview' => $draft?->isDraft() ? $records->preview($draft) : null,
            'periods' => AcademicPeriod::with('academicYear')
                ->orderByDesc('academic_year_id')->orderBy('sequence')->get(),
            'canManage' => Auth::user()->can('create', AcademicRecord::class),
        ]);
    }
}
