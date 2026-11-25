<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BillingModuleTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Team $team;
    private Customer $customer;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->admin = Admin::create([
            'name' => 'Billing Admin',
            'username' => 'billingadmin',
            'email' => 'billing-admin@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->team = Team::create([
            'team_code' => 'BILL-01',
            'team_name' => 'Billing Team',
            'username' => 'billteam',
            'password' => Hash::make('secret123'),
        ]);

        $this->customer = Customer::create([
            'name' => 'Acme Corp',
            'email' => 'acme@example.com',
            'phone' => '+919876543210',
            'status' => 'paying',
            'team_id' => $this->team->id,
            'purchases_count' => 1,
            'total_purchased' => 1000,
        ]);

        $this->productA = Product::create([
            'name' => 'CRM Pro License',
            'price' => 500.00,
            'minimum_closing_price' => 400.00,
            'duration' => '1y',
            'status' => 'active',
        ]);

        $this->productB = Product::create([
            'name' => 'Onboarding Setup',
            'price' => 250.00,
            'minimum_closing_price' => 200.00,
            'duration' => '30d',
            'status' => 'active',
        ]);
    }

    public function test_invoice_generation_with_multiple_items_discount_and_tax(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.billing.invoices.store'), [
                'customer_id' => $this->customer->id,
                'status' => 'sent',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'currency' => 'INR',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'tax_rate' => 18,
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'description' => 'CRM Pro License',
                        'duration' => '1 Year',
                        'quantity' => 2,
                        'unit_price' => 500,
                    ],
                    [
                        'product_id' => $this->productB->id,
                        'description' => 'Onboarding Setup',
                        'duration' => 'One-time',
                        'quantity' => 1,
                        'unit_price' => 250,
                    ],
                ],
            ]);

        $invoice = Invoice::firstOrFail();
        $response->assertRedirect(route('admin.billing.invoices.show', $invoice));

        // Subtotal = (2*500) + (1*250) = 1250
        // Discount 10% = 125 -> Taxable = 1125
        // Tax 18% of 1125 = 202.50 -> Grand Total = 1327.50
        $this->assertEquals(1250.00, (float) $invoice->subtotal);
        $this->assertEquals(125.00, (float) $invoice->discount_amount);
        $this->assertEquals(202.50, (float) $invoice->tax_amount);
        $this->assertEquals(1327.50, (float) $invoice->grand_total);
        $this->assertEquals(1327.50, (float) $invoice->balance_due);
        $this->assertCount(2, $invoice->items);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $invoice->getMorphClass(),
            'auditable_id' => $invoice->id,
            'event' => 'invoice.created',
        ]);
    }

    public function test_invoice_pdf_download_and_preview_rules(): void
    {
        $invoice = app(InvoiceService::class)->createInvoice([
            'customer_id' => $this->customer->id,
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Enterprise CRM', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ], $this->admin);

        $preview = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.invoices.preview', $invoice));
        $preview->assertOk();
        $preview->assertSee($invoice->invoice_number);
        $preview->assertSee('Acme Corp');
        $preview->assertDontSee('Enterprise Sales CRM & Automation Platform');
        $preview->assertDontSee('Portal URL');

        $pdf = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.invoices.pdf', $invoice));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_partial_full_payments_lock_credit_notes_and_refunds(): void
    {
        $invoice = app(InvoiceService::class)->createInvoice([
            'customer_id' => $this->customer->id,
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Annual Plan', 'quantity' => 1, 'unit_price' => 1000],
            ],
        ], $this->admin);

        // Partial payment 1: UPI
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.billing.invoices.payments.store', $invoice), [
                'amount' => 400,
                'payment_method' => 'upi',
                'transaction_reference' => 'UTR-001',
            ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->status);
        $this->assertEquals(400.00, (float) $invoice->amount_paid);
        $this->assertEquals(600.00, (float) $invoice->balance_due);

        // Partial payment 2: PhonePe -> completes full payment
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.billing.invoices.payments.store', $invoice), [
                'amount' => 600,
                'payment_method' => 'phonepe',
                'transaction_reference' => 'UTR-002',
            ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertEquals(1000.00, (float) $invoice->amount_paid);
        $this->assertEquals(0.00, (float) $invoice->balance_due);
        $this->assertNotNull($invoice->paid_at);

        // Paid invoice cannot be edited directly
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.invoices.edit', $invoice))
            ->assertRedirect(route('admin.billing.invoices.show', $invoice));

        // Issue Credit Note on paid invoice
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.billing.invoices.adjustments.store', $invoice), [
                'type' => 'credit_note',
                'amount' => 100,
                'reason' => 'Goodwill credit',
            ])->assertRedirect();
        $this->assertDatabaseHas('invoice_adjustments', ['invoice_id' => $invoice->id, 'amount' => 100]);

        // Full refund
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.billing.invoices.refunds.store', $invoice), [
                'amount' => 1000,
                'reason' => 'Customer requested cancellation',
            ])->assertRedirect();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_REFUNDED, $invoice->status);
        $this->assertEquals(1000.00, (float) $invoice->amount_refunded);
    }

    public function test_overdue_detection_and_recurring_invoice_deduplication(): void
    {
        $service = app(InvoiceService::class);

        $overdueInv = $service->createInvoice([
            'customer_id' => $this->customer->id,
            'status' => 'sent',
            'issue_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 300]],
        ], $this->admin);

        $updated = $service->syncOverdueInvoices();
        $this->assertSame(1, $updated);
        $this->assertSame(Invoice::STATUS_OVERDUE, $overdueInv->fresh()->status);

        $order = SalesOrder::create([
            'order_number' => 'ORD-BILL-001',
            'team_id' => $this->team->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'duration' => '1m',
            'product_value' => 500,
            'discount_amount' => 0,
            'closing_value' => 500,
            'amount_received' => 500,
            'payment_status' => 'paid',
            'order_status' => 'active',
        ]);

        // Recurring subscription
        $sub = Subscription::create([
            'order_id' => $order->id,
            'team_id' => $this->team->id,
            'customer_id' => $this->customer->id,
            'product_id' => $this->productA->id,
            'billing_cycle' => 'monthly',
            'price_snapshot' => 500,
            'amount_paid' => 500,
            'status' => 'active',
            'started_at' => now()->subMonth(),
            'expires_at' => now()->addDays(2),
            'renewal_date' => now()->startOfDay(),
        ]);

        $firstRenewal = $service->generateRecurringInvoice($sub, $this->admin);
        $this->assertNotNull($firstRenewal);

        // Reset renewal_date to same cycle and ensure duplicate is prevented
        $sub->update(['renewal_date' => $firstRenewal->billing_cycle_start]);
        $duplicate = $service->generateRecurringInvoice($sub, $this->admin);
        $this->assertNull($duplicate);
    }

    public function test_customer_billing_history_dashboard_expenses_and_permissions(): void
    {
        $service = app(InvoiceService::class);
        $inv = $service->createInvoice([
            'customer_id' => $this->customer->id,
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'CRM', 'quantity' => 1, 'unit_price' => 800]],
        ], $this->admin);
        $service->recordPayment($inv, ['amount' => 500, 'payment_method' => 'bank_transfer'], $this->admin);

        Expense::create([
            'expense_number' => 'EXP-202611-0001',
            'category' => 'infrastructure',
            'title' => 'Cloud Server',
            'amount' => 150,
            'currency' => 'USD',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'card',
            'recorded_by_admin_id' => $this->admin->id,
        ]);

        // Admin Customer Billing tab
        $custRes = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.show', $this->customer));
        $custRes->assertOk();
        $custRes->assertSee('Billing History');
        $custRes->assertSee($inv->invoice_number);

        // Admin Billing Dashboard
        $dashRes = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.dashboard', ['range' => 'month']));
        $dashRes->assertOk();
        $dashRes->assertSee('500.00');
        $dashRes->assertSee('300.00'); // outstanding
        $dashRes->assertSee('350.00'); // net profit = 500 - 150

        // Employee permissions: Sales Executive cannot access admin billing settings
        // Logout admin guard before testing employee isolation
        auth()->guard('admin')->logout();

        $employee = User::create([
            'name' => 'Sales Rep',
            'username' => 'salesrep',
            'email' => 'sales@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $this->team->id,
        ]);

        $this->actingAs($employee, 'employee')
            ->get(route('admin.billing.settings'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($employee, 'employee')
            ->get(route('employee.billing.index'))
            ->assertOk();

        // Employee cannot view another user's invoice without team grant
        $this->actingAs($employee, 'employee')
            ->get(route('employee.billing.show', $inv))
            ->assertForbidden();
    }

    public function test_invoice_template_selection_in_settings_invoice_and_preview(): void
    {
        // 1. Update default template in Billing Settings
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.billing.settings.update'), [
                'company_name' => 'LeadX Corp',
                'invoice_prefix' => 'INV',
                'next_sequence' => 1,
                'default_currency' => 'USD',
                'default_tax_rate' => 0,
                'default_tax_label' => 'Tax',
                'default_due_days' => 14,
                'default_template' => 'modern_emerald',
                'pdf_paper_size' => 'a4',
            ])->assertRedirect();

        $this->assertSame('modern_emerald', Setting::billing()['default_template']);

        // 2. Create invoice with explicit template override
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.billing.invoices.store'), [
                'customer_id' => $this->customer->id,
                'status' => 'sent',
                'issue_date' => now()->toDateString(),
                'template' => 'minimal_slate',
                'items' => [['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 200]],
            ])->assertRedirect();

        $invoice = Invoice::latest('id')->firstOrFail();
        $this->assertSame('minimal_slate', $invoice->template);

        $preview = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.invoices.preview', $invoice));
        $preview->assertOk();
        $preview->assertSee('tpl-minimal_slate');

        // 3. Preview with query override
        $overridePreview = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.invoices.preview', ['invoice' => $invoice, 'template' => 'modern_emerald']));
        $overridePreview->assertOk();
        $overridePreview->assertSee('tpl-modern_emerald');

        // 4. Visual template selector cards render on Create Invoice and Billing Settings
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.invoices.create'))
            ->assertOk()
            ->assertSee('data-template-selector="template"', false)
            ->assertSee('Classic Navy &amp; Green', false)
            ->assertSee('Modern Executive')
            ->assertSee('Minimal Clean Print');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.settings'))
            ->assertOk()
            ->assertSee('data-template-selector="default_template"', false);
    }
}
