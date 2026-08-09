<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Invoice $invoice;

    public bool $showAddItem = false;

    public string $item_description = '';

    public string $item_amount = '';

    public bool $showAddDiscount = false;

    public string $discount_type = 'discount';

    public string $discount_mode = 'amount';

    public string $discount_amount = '';

    public string $discount_percentage = '';

    public string $discount_reason = '';

    public bool $showRecordPayment = false;

    public string $payment_amount = '';

    public string $payment_method = 'cash';

    public string $payment_date = '';

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $invoice;
        $this->payment_date = now()->toDateString();
    }

    public function addItem(): void
    {
        $this->authorize('manageItems', $this->invoice);

        $validated = $this->validate([
            'item_description' => ['required', 'string', 'max:150'],
            'item_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->invoice->items()->create([
            'description' => $validated['item_description'],
            'amount' => $validated['item_amount'],
        ]);

        $this->reset(['item_description', 'item_amount', 'showAddItem']);
        $this->refreshInvoice();
    }

    public function removeItem(int $itemId): void
    {
        $this->authorize('manageItems', $this->invoice);
        $this->invoice->items()->where('id', $itemId)->delete();
        $this->refreshInvoice();
    }

    public function addDiscount(): void
    {
        $this->authorize('addDiscount', $this->invoice);

        $rules = [
            'discount_type' => ['required', Rule::in(['discount', 'scholarship'])],
            'discount_reason' => ['required', 'string', 'max:255'],
        ];

        if ($this->discount_mode === 'percentage') {
            $rules['discount_percentage'] = ['required', 'numeric', 'min:0', 'max:100'];
        } else {
            $rules['discount_amount'] = ['required', 'numeric', 'min:0'];
        }

        $validated = $this->validate($rules);

        $approverId = Auth::user()->staff?->id;
        abort_unless($approverId, 403, 'Only staff members can approve discounts.');

        $this->invoice->discounts()->create([
            'type' => $validated['discount_type'],
            'amount' => $this->discount_mode === 'amount' ? $validated['discount_amount'] : null,
            'percentage' => $this->discount_mode === 'percentage' ? $validated['discount_percentage'] : null,
            'reason' => $validated['discount_reason'],
            'approved_by' => $approverId,
        ]);

        $this->reset(['discount_amount', 'discount_percentage', 'discount_reason', 'showAddDiscount']);
        $this->discount_type = 'discount';
        $this->discount_mode = 'amount';
        $this->refreshInvoice();
        $this->invoice->refreshStatus();
    }

    public function recordPayment(): void
    {
        $this->authorize('recordPayment', $this->invoice);

        $validated = $this->validate([
            'payment_amount' => ['required', 'numeric', 'min:0.01', 'max:'.max($this->invoice->balance(), 0.01)],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'other'])],
            'payment_date' => ['required', 'date'],
        ]);

        $receiverId = Auth::user()->staff?->id;
        abort_unless($receiverId, 403, 'Only staff members can record payments.');

        $sequence = \App\Models\Payment::whereYear('paid_at', now()->year)->count() + 1;

        $this->invoice->payments()->create([
            'amount' => $validated['payment_amount'],
            'method' => $validated['payment_method'],
            'paid_at' => $validated['payment_date'],
            'received_by' => $receiverId,
            'receipt_number' => sprintf('RCT-%s-%04d', now()->year, $sequence),
        ]);

        $this->reset(['payment_amount', 'showRecordPayment']);
        $this->payment_method = 'cash';
        $this->payment_date = now()->toDateString();
        $this->refreshInvoice();
        $this->invoice->refreshStatus();
    }

    public function voidInvoice(): void
    {
        $this->authorize('void', $this->invoice);
        $this->invoice->update(['status' => 'void']);
        $this->refreshInvoice();
    }

    protected function refreshInvoice(): void
    {
        $this->invoice->refresh()->load('items', 'discounts.approver', 'payments.receivedBy');
    }

    public function render()
    {
        return view('livewire.invoices.show', [
            'hasStaffProfile' => Auth::user()->staff !== null,
        ]);
    }
}
