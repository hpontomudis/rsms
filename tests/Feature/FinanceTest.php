<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Invoice;
use App\Models\Position;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_balance_combines_items_discounts_and_payments(): void
    {
        [$invoice, $staff] = $this->makeInvoiceWithItems();

        $this->assertSame(1200000.0, $invoice->itemsTotal());

        $invoice->discounts()->create([
            'type' => 'discount', 'percentage' => 10, 'reason' => 'sibling discount', 'approved_by' => $staff->id,
        ]);
        $invoice->refresh();
        $this->assertSame(120000.0, $invoice->discountsTotal());

        $invoice->payments()->create([
            'amount' => 500000, 'method' => 'cash', 'paid_at' => now(), 'received_by' => $staff->id, 'receipt_number' => 'RCT-TEST-0001',
        ]);
        $invoice->refresh();

        $this->assertSame(580000.0, $invoice->balance());

        $invoice->refreshStatus();
        $this->assertSame('partially_paid', $invoice->fresh()->status);
    }

    public function test_invoice_becomes_paid_once_balance_reaches_zero(): void
    {
        [$invoice, $staff] = $this->makeInvoiceWithItems();

        $invoice->payments()->create([
            'amount' => 1200000, 'method' => 'cash', 'paid_at' => now(), 'received_by' => $staff->id, 'receipt_number' => 'RCT-TEST-0002',
        ]);
        $invoice->refresh();
        $invoice->refreshStatus();

        $this->assertSame(0.0, $invoice->fresh(['payments'])->balance());
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_a_voided_invoice_never_auto_recalculates_status(): void
    {
        [$invoice, $staff] = $this->makeInvoiceWithItems();
        $invoice->update(['status' => 'void']);

        $invoice->payments()->create([
            'amount' => 1200000, 'method' => 'cash', 'paid_at' => now(), 'received_by' => $staff->id, 'receipt_number' => 'RCT-TEST-0003',
        ]);
        $invoice->refresh();
        $invoice->refreshStatus();

        $this->assertSame('void', $invoice->fresh()->status);
    }

    public function test_items_can_only_be_edited_before_a_payment_exists(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$invoice, $staff] = $this->makeInvoiceWithItems();

        $financeUser = User::factory()->create();
        $financeUser->assignRole('finance_staff');

        $this->assertTrue($financeUser->can('manageItems', $invoice));

        $invoice->payments()->create([
            'amount' => 100000, 'method' => 'cash', 'paid_at' => now(), 'received_by' => $staff->id, 'receipt_number' => 'RCT-TEST-0004',
        ]);
        $invoice->refresh();

        $this->assertFalse($financeUser->can('manageItems', $invoice));
    }

    public function test_principal_can_approve_discounts_without_general_finance_manage(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$invoice] = $this->makeInvoiceWithItems();

        $principal = User::factory()->create();
        $principal->assignRole('principal');

        $this->assertTrue($principal->can('addDiscount', $invoice));
        $this->assertFalse($principal->can('create', Invoice::class));
        $this->assertFalse($principal->can('recordPayment', $invoice));
    }

    public function test_finance_staff_can_manage_invoices_but_teacher_cannot(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $financeUser = User::factory()->create();
        $financeUser->assignRole('finance_staff');
        $this->assertTrue($financeUser->can('create', Invoice::class));

        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('teacher');
        $this->assertFalse($teacherUser->can('create', Invoice::class));
        $this->assertFalse($teacherUser->can('viewAny', Invoice::class));
    }

    /**
     * @return array{0: Invoice, 1: Staff}
     */
    private function makeInvoiceWithItems(): array
    {
        $ay = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_current' => true,
        ]);
        $student = Student::create([
            'student_number' => 'STU-001', 'first_name' => 'Andi', 'last_name' => 'Wijaya',
            'date_of_birth' => '2015-03-12', 'gender' => 'male', 'enrollment_date' => '2026-07-01',
        ]);
        $position = Position::create(['title' => 'Finance Officer']);
        $staff = Staff::create([
            'staff_number' => 'STF-'.uniqid(), 'first_name' => 'Sri', 'last_name' => 'Wulandari',
            'position_id' => $position->id, 'phone' => '0812-0000-0099', 'hire_date' => '2020-07-01',
        ]);

        $invoice = Invoice::create([
            'student_id' => $student->id,
            'academic_year_id' => $ay->id,
            'invoice_number' => 'INV-TEST-0001',
            'issue_date' => now(),
            'due_date' => now()->addMonth(),
            'status' => 'unpaid',
        ]);
        $invoice->items()->create(['description' => 'Tuition', 'amount' => 1000000]);
        $invoice->items()->create(['description' => 'Uniform', 'amount' => 200000]);
        $invoice->load('items', 'discounts', 'payments');

        return [$invoice, $staff];
    }
}
