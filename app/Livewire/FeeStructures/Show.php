<?php

namespace App\Livewire\FeeStructures;

use App\Models\FeeStructure;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public FeeStructure $feeStructure;

    public bool $showAddItem = false;

    public string $item_name = '';

    public string $item_amount = '';

    public function mount(FeeStructure $feeStructure): void
    {
        $this->authorize('view', $feeStructure);
        $this->feeStructure = $feeStructure;
    }

    public function addItem(): void
    {
        $this->authorize('update', $this->feeStructure);

        $validated = $this->validate([
            'item_name' => ['required', 'string', 'max:100'],
            'item_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->feeStructure->items()->create([
            'name' => $validated['item_name'],
            'amount' => $validated['item_amount'],
        ]);

        $this->reset(['item_name', 'item_amount', 'showAddItem']);
        $this->feeStructure->refresh();
    }

    public function removeItem(int $itemId): void
    {
        $this->authorize('update', $this->feeStructure);
        $this->feeStructure->items()->where('id', $itemId)->delete();
        $this->feeStructure->refresh();
    }

    public function render()
    {
        return view('livewire.fee-structures.show');
    }
}
