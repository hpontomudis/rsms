<?php

namespace App\Livewire\Staff;

use App\Models\Staff;
use App\Services\Import\StaffImportRow;
use App\Services\Import\StaffImportService;
use App\Services\Import\StaffImportValidator;
use App\Services\Import\StaffTemplateBuilder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Upload -> validate -> preview -> confirm -> import -> credential handoff
 * (P2C). Every row is validated before any row is imported (see
 * StaffImportValidator/StaffImportService docblocks) -- "Confirm Import"
 * is disabled unless the whole file is clean.
 */
#[Layout('layouts.app')]
class Import extends Component
{
    use WithFileUploads;

    public $file;

    /** @var StaffImportRow[] */
    public array $preview = [];

    public bool $validated = false;

    /** @var array{staff: Staff, credential: ?array}[] */
    public array $imported = [];

    /** @var array{name: string, email: string, password: string, role: string, status: string}[] */
    public array $credentials = [];

    public function mount(): void
    {
        $this->authorize('import', Staff::class);
    }

    public function downloadTemplate(StaffTemplateBuilder $builder)
    {
        $this->authorize('import', Staff::class);

        return $builder->download();
    }

    public function upload(StaffImportValidator $validator): void
    {
        $this->authorize('import', Staff::class);

        $this->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:5120']]);

        $this->preview = $validator->validate($this->file->getRealPath(), Auth::user());
        $this->validated = true;
        $this->file = null; // never persisted beyond this request
    }

    public function confirmImport(StaffImportService $importer): void
    {
        $this->authorize('import', Staff::class);

        if (! $this->validated || $this->preview === [] || collect($this->preview)->contains(fn (StaffImportRow $r) => ! $r->isValid())) {
            return;
        }

        $this->imported = $importer->import($this->preview, Auth::user());
        $this->credentials = collect($this->imported)
            ->pluck('credential')
            ->filter()
            ->values()
            ->all();

        $this->preview = [];
        $this->validated = false;
    }

    public function downloadCredentials(\App\Services\Import\CredentialExportBuilder $builder)
    {
        $this->authorize('import', Staff::class);

        return $builder->download($this->credentials);
    }

    public function render()
    {
        return view('livewire.staff.import');
    }
}
