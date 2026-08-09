<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('finance.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('finance.manage');
    }

    /**
     * Line items may only be adjusted before any payment has been recorded
     * against the invoice — once money has moved, corrections go through
     * a new discount/payment entry instead of editing history.
     */
    public function manageItems(User $user, Invoice $invoice): bool
    {
        return $user->can('finance.manage')
            && $invoice->status !== 'void'
            && $invoice->payments->isEmpty();
    }

    /**
     * Recording a payment or voiding an invoice are finance-office actions.
     */
    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->can('finance.manage') && $invoice->status !== 'void';
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can('finance.manage') && $invoice->status !== 'void';
    }

    /**
     * Discounts/scholarships can additionally be approved by the principal
     * per PRD §7 ("approve certain actions e.g. discounts"), even though
     * they don't hold general finance.manage access.
     */
    public function addDiscount(User $user, Invoice $invoice): bool
    {
        return $user->can('finance.discounts.approve') && $invoice->status !== 'void';
    }
}
