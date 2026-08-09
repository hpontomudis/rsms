<?php

namespace App\Livewire\Students;

use App\Models\Student;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Edit extends Component
{
    public Student $student;

    public string $student_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $date_of_birth = '';

    public string $gender = '';

    public string $enrollment_date = '';

    public string $status = '';

    public function mount(Student $student): void
    {
        $this->authorize('update', $student);
        $this->student = $student;
        $this->student_number = $student->student_number;
        $this->first_name = $student->first_name;
        $this->last_name = $student->last_name;
        $this->date_of_birth = $student->date_of_birth->toDateString();
        $this->gender = $student->gender;
        $this->enrollment_date = $student->enrollment_date->toDateString();
        $this->status = $student->status;
    }

    public function save()
    {
        $this->authorize('update', $this->student);

        $validated = $this->validate([
            'student_number' => ['required', 'string', 'max:50', Rule::unique('students', 'student_number')->ignore($this->student->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'enrollment_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['active', 'graduated', 'withdrawn', 'transferred'])],
        ]);

        $this->student->update($validated);

        session()->flash('status', "{$this->student->fullName()} was updated.");

        return $this->redirect(route('students.show', $this->student), navigate: true);
    }

    public function render()
    {
        return view('livewire.students.edit');
    }
}
