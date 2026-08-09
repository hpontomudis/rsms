<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
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
            'todaysClasses' => $isTeacher && $user->can('attendance.record')
                ? $this->todaysClassesFor($staffId)
                : null,
            'schoolAttendanceToday' => (! $isTeacher && $user->can('attendance.view'))
                ? $this->schoolAttendanceToday()
                : null,
            'financeSummary' => $user->can('finance.view') ? $this->financeSummary() : null,
        ]);
    }

    private function financeSummary(): array
    {
        $outstanding = Invoice::where('status', '!=', 'void')
            ->with('items', 'discounts', 'payments')
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->balance());

        $paidToday = Payment::where('paid_at', now()->toDateString())->sum('amount');

        $overdueCount = Invoice::whereIn('status', ['unpaid', 'partially_paid'])
            ->where('due_date', '<', now()->toDateString())
            ->count();

        return [
            'outstanding' => (float) $outstanding,
            'paidToday' => (float) $paidToday,
            'overdueCount' => $overdueCount,
        ];
    }

    private function todaysClassesFor(int $staffId)
    {
        $currentYear = AcademicYear::current();

        if (! $currentYear) {
            return collect();
        }

        $classes = SchoolClass::taughtBy($staffId)
            ->where('academic_year_id', $currentYear->id)
            ->with('grade')
            ->orderBy('name')
            ->get();

        $takenClassIds = Attendance::whereIn('class_id', $classes->pluck('id'))
            ->where('date', now()->toDateString())
            ->pluck('class_id');

        return $classes->map(fn ($class) => (object) [
            'schoolClass' => $class,
            'taken' => $takenClassIds->contains($class->id),
        ]);
    }

    private function schoolAttendanceToday(): array
    {
        $sessions = Attendance::where('date', now()->toDateString())->with('records')->get();
        $totalClassesToday = $sessions->count();

        $totalRecords = $sessions->sum(fn ($session) => $session->records->count());
        $presentOrLate = $sessions->sum(
            fn ($session) => $session->records->whereIn('status', ['present', 'late'])->count()
        );

        return [
            'classesTaken' => $totalClassesToday,
            'rate' => $totalRecords > 0 ? round($presentOrLate / $totalRecords * 100) : null,
        ];
    }
}
