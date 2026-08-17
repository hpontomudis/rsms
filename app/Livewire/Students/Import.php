<?php

namespace App\Livewire\Students;

use App\Models\Student;
use App\Services\Import\StudentImportRow;
use App\Services\Import\StudentImportService;
use App\Services\Import\StudentImportValidator;
use App\Services\Import\StudentTemplateBuilder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Upload -> validate -> preview -> confirm -> import (P2D). No account
 * provisioning or credential handoff step here -- Students don't get
 * RSMS logins (see StudentTemplateBuilder's docblock for the class
 * enrollment exclusion too).
 */
#[Layout('layouts.app')]
class Import extends Component
{
    use WithFileUploads;

    public $file;

    /** @var StudentImportRow[] */
    public array $preview = [];

    public bool $validated = false;

    /** @var Student[] */
    public array $imported = [];

    public function mount(): void
    {
        $this->authorize('import', Student::class);
    }

    public function downloadTemplate(StudentTemplateBuilder $builder)
    {
        $this->authorize('import', Student::class);

        return $builder->download();
    }

    public function upload(StudentImportValidator $validator): void
    {
        $this->authorize('import', Student::class);

        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);

        $this->preview = $validator->validate($this->file->getRealPath());
        $this->validated = true;
        $this->file = null;
    }

    public function confirmImport(StudentImportService $importer): void
    {
        $this->authorize('import', Student::class);

        if (! $this->validated || $this->preview === [] || collect($this->preview)->contains(fn (StudentImportRow $r) => ! $r->isValid())) {
            return;
        }

        $this->imported = $importer->import($this->preview);
        $this->preview = [];
        $this->validated = false;
    }

    public function render()
    {
        return view('livewire.students.import');
    }
}
