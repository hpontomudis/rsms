<?php

namespace App\Livewire\Invoices;

use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Create extends Component
{
    public string $student_id = '';

    public string $academic_year_id = '';

    public string $fee_structure_id = '';

    public string $issue_date = '';

    public string $due_date = '';

    public function mount(): void
    {
        $this->authorize('create', Invoice::class);
        $this->academic_year_id = (string) (AcademicYear::current()?->id ?? '');
        $this->issue_date = now()->toDateString();
        $this->due_date = now()->addDays(30)->toDateString();
    }

    public function save()
    {
        $this->authorize('create', Invoice::class);

        $validated = $this->validate([
            'student_id' => ['required', 'exists:students,id'],
            'academic_year_id' => ['required', 'exists:academic_years,id'],
            'fee_structure_id' => ['required', 'exists:fee_structures,id'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
        ]);

        $academicYear = AcademicYear::findOrFail($validated['academic_year_id']);
        $feeStructure = FeeStructure::with('items')->findOrFail($validated['fee_structure_id']);

        $invoice = DB::transaction(function () use ($validated, $academicYear, $feeStructure) {
            $sequence = Invoice::where('academic_year_id', $academicYear->id)->count() + 1;

            $invoice = Invoice::create([
                'student_id' => $validated['student_id'],
                'academic_year_id' => $academicYear->id,
                'invoice_number' => sprintf('INV-%s-%04d', $academicYear->start_date->year, $sequence),
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'status' => 'unpaid',
            ]);

            foreach ($feeStructure->items as $item) {
                $invoice->items()->create([
                    'description' => $item->name,
                    'amount' => $item->amount,
                ]);
            }

            return $invoice;
        });

        session()->flash('status', "Invoice {$invoice->invoice_number} was created.");

        return $this->redirect(route('invoices.show', $invoice), navigate: true);
    }

    public function render()
    {
        return view('livewire.invoices.create', [
            'students' => Student::orderBy('first_name')->get(),
            'academicYears' => AcademicYear::orderByDesc('start_date')->get(),
            'feeStructures' => $this->academic_year_id !== ''
                ? FeeStructure::where('academic_year_id', $this->academic_year_id)->with('grade')->get()
                : collect(),
        ]);
    }
}
