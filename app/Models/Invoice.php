<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_id', 'academic_year_id', 'invoice_number', 'issue_date', 'due_date', 'status'])]
class Invoice extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(Discount::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function itemsTotal(): float
    {
        return (float) $this->items->sum('amount');
    }

    public function discountsTotal(): float
    {
        $itemsTotal = $this->itemsTotal();

        return (float) $this->discounts->sum(
            fn (Discount $discount) => $discount->amount !== null
                ? (float) $discount->amount
                : $itemsTotal * ((float) $discount->percentage / 100)
        );
    }

    public function paidTotal(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function balance(): float
    {
        return round($this->itemsTotal() - $this->discountsTotal() - $this->paidTotal(), 2);
    }

    /**
     * Recompute status from items/discounts/payments. A voided invoice is
     * a terminal state and never auto-recalculated back to unpaid/paid.
     */
    public function refreshStatus(): void
    {
        if ($this->status === 'void') {
            return;
        }

        $status = match (true) {
            $this->balance() <= 0 => 'paid',
            $this->paidTotal() > 0 => 'partially_paid',
            default => 'unpaid',
        };

        if ($status !== $this->status) {
            $this->update(['status' => $status]);
        }
    }
}
