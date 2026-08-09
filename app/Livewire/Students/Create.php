<?php

namespace App\Livewire\Students;

use App\Models\Student;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $student_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $date_of_birth = '';

    public string $gender = '';

    public string $enrollment_date = '';

    public function mount(): void
    {
        $this->authorize('create', Student::class);
        $this->enrollment_date = now()->toDateString();
    }

    public function save()
    {
        $this->authorize('create', Student::class);

        $validated = $this->validate([
            'student_number' => ['required', 'string', 'max:50', Rule::unique('students', 'student_number')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'enrollment_date' => ['required', 'date'],
        ]);

        $student = Student::create($validated);

        session()->flash('status', "{$student->fullName()} was added.");

        return $this->redirect(route('students.show', $student), navigate: true);
    }

    public function render()
    {
        return view('livewire.students.create');
    }
}
