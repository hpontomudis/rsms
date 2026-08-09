<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        $user = Auth::user();
        $isTeacher = $user->hasRole('teacher');
        $staffId = $user->staff?->id ?? 0;

        $studentCount = null;
        if (Route::has('students.index') && $user->can('students.view')) {
            $studentCount = $isTeacher
                ? Student::taughtBy($staffId)->count()
                : Student::count();
        }

        $classCount = null;
        if (Route::has('classes.index') && $user->can('classes.view')) {
            $classCount = $isTeacher
                ? SchoolClass::taughtBy($staffId)->count()
                : SchoolClass::count();
        }

        return view('livewire.dashboard', [
            'studentCount' => $studentCount,
            // Guardians/Staff have no teacher-scoped view -- a teacher never
            // holds the underlying permission, so these stay null for them.
            'guardianCount' => Route::has('guardians.index') && $user->can('guardians.view') ? Guardian::count() : null,
            'staffCount' => Route::has('staff.index') && $user->can('staff.view') ? Staff::count() : null,
            'classCount' => $classCount,
            'recentActivity' => $user->can('audit-logs.view')
                ? AuditLog::with('user')->latest('id')->limit(8)->get()
                : collect(),
        ]);
    }
}
