<?php

namespace App\Livewire\Attendance;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AttendanceRecord;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Take extends Component
{
    #[Url]
    public string $class_id = '';

    #[Url]
    public string $date = '';

    /** @var array<int, string> student_id => status */
    public array $statuses = [];

    public bool $saved = false;

    public function mount(): void
    {
        // Bare permission check (spatie/laravel-permission registers each
        // permission name as a Gate ability via its own Gate::before hook).
        $this->authorize('attendance.record');

        if ($this->date === '') {
            $this->date = now()->toDateString();
        }

        $myClasses = $this->availableClasses();

        if ($this->class_id === '' && $myClasses->count() === 1) {
            $this->class_id = (string) $myClasses->first()->id;
        }

        if ($this->class_id !== '') {
            $this->loadRoster();
        }
    }

    public function updatedClassId(): void
    {
        $this->saved = false;
        $this->loadRoster();
    }

    public function updatedDate(): void
    {
        $this->saved = false;
        $this->loadRoster();
    }

    public function setStatus(int $studentId, string $status): void
    {
        if (! $this->canEdit()) {
            return;
        }

        $this->statuses[$studentId] = $status;
    }

    public function markAllPresent(): void
    {
        if (! $this->canEdit()) {
            return;
        }

        foreach ($this->statuses as $studentId => $status) {
            $this->statuses[$studentId] = 'present';
        }
    }

    public function save(): void
    {
        $class = SchoolClass::findOrFail($this->class_id);
        $this->authorize('recordFor', [Attendance::class, $class]);

        $existing = Attendance::where('class_id', $this->class_id)->where('date', $this->date)->first();

        if ($existing) {
            $this->authorize('update', $existing);
        }

        $staffId = Auth::user()->staff?->id;
        abort_unless($staffId, 403, 'Only staff members can record attendance.');

        DB::transaction(function () use ($class, $staffId, $existing) {
            $attendance = $existing ?? Attendance::create([
                'class_id' => $class->id,
                'academic_year_id' => $class->academic_year_id,
                'date' => $this->date,
                'taken_by' => $staffId,
            ]);

            foreach ($this->statuses as $studentId => $status) {
                AttendanceRecord::updateOrCreate(
                    ['attendance_id' => $attendance->id, 'student_id' => $studentId],
                    ['status' => $status]
                );
            }
        });

        $this->saved = true;
    }

    protected function loadRoster(): void
    {
        $this->statuses = [];

        if ($this->class_id === '') {
            return;
        }

        $class = SchoolClass::findOrFail($this->class_id);
        $this->authorize('recordFor', [Attendance::class, $class]);

        $students = $class->students()->wherePivot('status', 'active')->orderBy('first_name')->get();

        $existing = Attendance::where('class_id', $this->class_id)
            ->where('date', $this->date)
            ->with('records')
            ->first();

        foreach ($students as $student) {
            $record = $existing?->records->firstWhere('student_id', $student->id);
            $this->statuses[$student->id] = $record->status ?? 'present';
        }
    }

    protected function canEdit(): bool
    {
        if ($this->class_id === '') {
            return false;
        }

        $existing = Attendance::where('class_id', $this->class_id)->where('date', $this->date)->first();

        if (! $existing) {
            return true;
        }

        return Auth::user()->can('update', $existing);
    }

    protected function availableClasses()
    {
        $user = Auth::user();
        $currentYear = AcademicYear::current();

        if (! $currentYear) {
            return collect();
        }

        $query = SchoolClass::where('academic_year_id', $currentYear->id)->with('grade');

        if ($user->hasRole('teacher')) {
            $query->taughtBy($user->staff?->id ?? 0);
        }

        return $query->orderBy('name')->get();
    }

    public function render()
    {
        $class = $this->class_id !== '' ? SchoolClass::with('grade')->find($this->class_id) : null;

        $students = $class
            ? $class->students()->wherePivot('status', 'active')->orderBy('first_name')->get()
            : collect();

        return view('livewire.attendance.take', [
            'classes' => $this->availableClasses(),
            'schoolClass' => $class,
            'students' => $students,
            'editable' => $this->canEdit(),
            'hasStaffProfile' => Auth::user()->staff !== null,
        ]);
    }
}
