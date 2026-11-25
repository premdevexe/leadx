<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Payment;
use App\Models\PermissionPreset;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\InvoiceNumberService;
use App\Services\Billing\InvoiceService;
use App\Services\PaymentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

    public function test_bug1_two_payment_flows_preserve_order_totals_and_verify_syncs_invoice(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'ORD-SYNC-001',
            'team_id' => $this->team->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'duration' => '1y',
            'product_value' => 8000,
            'discount_amount' => 0,
            'closing_value' => 8000,
            'amount_received' => 0,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ]);

        // Direct verified payment of 5000 on the order via PaymentService::verify()
        $directPayment = Payment::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'DIRECT-5000',
            'amount' => 5000,
            'currency' => 'USD',
            'payment_date' => now(),
            'payment_status' => 'pending',
            'source' => 'manual',
        ]);
        app(PaymentService::class)->verify($directPayment, $this->admin->id);

        $order->refresh();
        $this->assertEquals(5000.00, (float) $order->amount_received);
        $this->assertSame('partially_paid', $order->payment_status);

        // Now create an invoice for that order with a confirmed payment of 3000
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Balance Invoice', 'quantity' => 1, 'unit_price' => 3000]],
        ], $this->admin);

        $invoiceService->recordPayment($invoice, [
            'amount' => 3000,
            'payment_method' => 'upi',
            'transaction_reference' => 'INV-3000',
        ], $this->admin);

        // Order total must be 8000 (5000 + 3000), NOT 3000!
        $order->refresh();
        $this->assertEquals(8000.00, (float) $order->amount_received);
        $this->assertSame('paid', $order->payment_status);

        // Now test verifying an invoice-linked pending payment via PaymentService::verify()
        $invoice2 = $invoiceService->createInvoice([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Addon', 'quantity' => 1, 'unit_price' => 1000]],
        ], $this->admin);

        $pendingInvPayment = $invoiceService->recordPayment($invoice2, [
            'amount' => 600,
            'payment_method' => 'upi',
            'confirmed' => false,
        ]);
        $this->assertSame('sent', $invoice2->fresh()->status);

        app(PaymentService::class)->verify($pendingInvPayment, $this->admin->id);
        $invoice2->refresh();
        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice2->status);
        $this->assertEquals(600.00, (float) $invoice2->amount_paid);
    }

    public function test_bug2_uploads_derive_safe_extension_from_mime_and_htaccess_blocks_php(): void
    {
        $this->assertFileExists(public_path('uploads/.htaccess'));
        $htaccess = file_get_contents(public_path('uploads/.htaccess'));
        $this->assertStringContainsString('php_flag engine off', $htaccess);
        $this->assertStringContainsString('Require all denied', $htaccess);

        // Minimal valid 1x1 PNG binary
        $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        // 1. Direct .php extension is rejected by validation
        $tmpPhp = tempnam(sys_get_temp_dir(), 'polyphp');
        file_put_contents($tmpPhp, $pngBinary);
        $disguisedPhp = new UploadedFile($tmpPhp, 'shell.php', 'image/png', null, true);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.billing.expenses.store'), [
                'title' => 'Blocked PHP Upload',
                'category' => 'software',
                'amount' => 25,
                'expense_date' => now()->toDateString(),
                'payment_method' => 'card',
                'receipt' => $disguisedPhp,
            ])->assertSessionHasErrors('receipt');

        // 2. Polyglot with non-php executable extension (e.g. .html / .pht) carrying PNG content is stored as .png from MIME sniff
        $tmpPoly = tempnam(sys_get_temp_dir(), 'polyhtml');
        file_put_contents($tmpPoly, $pngBinary);
        $disguisedHtml = new UploadedFile($tmpPoly, 'payload.html', 'image/png', null, true);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.billing.expenses.store'), [
                'title' => 'Disguised Receipt',
                'category' => 'software',
                'amount' => 25,
                'expense_date' => now()->toDateString(),
                'payment_method' => 'card',
                'receipt' => $disguisedHtml,
            ])->assertSessionHasNoErrors()->assertRedirect();

        $expense = Expense::latest('id')->firstOrFail();
        $this->assertNotNull($expense->receipt_path);
        $this->assertStringEndsWith('.png', $expense->receipt_path);
        $this->assertStringNotContainsString('.html', $expense->receipt_path);
        if (file_exists(public_path($expense->receipt_path))) {
            @unlink(public_path($expense->receipt_path));
        }

        // Valid PNG logo upload keeps .png
        $validPng = UploadedFile::fake()->image('company-logo.png', 100, 100);
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.billing.settings.update'), [
                'company_name' => 'LeadX Corp',
                'invoice_prefix' => 'INV',
                'next_sequence' => 1,
                'default_currency' => 'USD',
                'default_tax_rate' => 0,
                'default_tax_label' => 'Tax',
                'default_due_days' => 14,
                'default_template' => 'classic_navy',
                'pdf_paper_size' => 'a4',
                'billing_logo' => $validPng,
            ])->assertRedirect();

        $logoPath = Setting::billing()['billing_logo'] ?? '';
        $this->assertStringEndsWith('.png', $logoPath);
        if ($logoPath && file_exists(public_path($logoPath))) {
            @unlink(public_path($logoPath));
        }
    }

    public function test_bug3_and_bug4_overpayment_rejected_and_refund_types_sync_order_status(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'ORD-REF-001',
            'team_id' => $this->team->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'duration' => '1y',
            'product_value' => 1000,
            'discount_amount' => 0,
            'closing_value' => 1000,
            'amount_received' => 0,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ]);

        $service = app(InvoiceService::class);
        $invoice = $service->createInvoice([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Suite', 'quantity' => 1, 'unit_price' => 1000]],
        ], $this->admin);

        // First payment of 700 succeeds; second payment of 400 (sum 1100 > 1000) must fail
        $service->recordPayment($invoice, ['amount' => 700, 'payment_method' => 'upi'], $this->admin);
        try {
            $service->recordPayment($invoice, ['amount' => 400, 'payment_method' => 'upi'], $this->admin);
            $this->fail('Expected ValidationException on overpayment.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        // Complete full payment (300)
        $service->recordPayment($invoice, ['amount' => 300, 'payment_method' => 'upi'], $this->admin);
        $this->assertSame('paid', $order->fresh()->payment_status);

        // Partial refund 1: 400 -> type=partial, order=partially_refunded
        $ref1 = $service->recordRefund($invoice, ['amount' => 400, 'reason' => 'Partial refund'], $this->admin);
        $this->assertSame('partial', $ref1->type);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);

        // Second refund covering remaining 600 -> type=full (400+600 >= 1000), order=refunded
        $ref2 = $service->recordRefund($invoice, ['amount' => 600, 'reason' => 'Remaining refund'], $this->admin);
        $this->assertSame('full', $ref2->type);
        $this->assertSame(Invoice::STATUS_REFUNDED, $invoice->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_bug5_deleting_order_nullifies_payment_order_id_instead_of_cascade_deleting(): void
    {
        $order = SalesOrder::create([
            'order_number' => 'ORD-DEL-001',
            'team_id' => $this->team->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'product_id' => $this->productA->id,
            'product_name' => $this->productA->name,
            'duration' => '1y',
            'product_value' => 500,
            'discount_amount' => 0,
            'closing_value' => 500,
            'amount_received' => 0,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ]);

        $service = app(InvoiceService::class);
        $invoice = $service->createInvoice([
            'customer_id' => $this->customer->id,
            'order_id' => $order->id,
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'items' => [['description' => 'Service', 'quantity' => 1, 'unit_price' => 500]],
        ], $this->admin);

        $payment = $service->recordPayment($invoice, ['amount' => 500, 'payment_method' => 'cash'], $this->admin);
        $order->delete();

        $payment->refresh();
        $this->assertNull($payment->order_id);
        $this->assertSame($invoice->id, $payment->invoice_id);
        $this->assertEquals(500.00, (float) $invoice->fresh()->amount_paid);
    }

    public function test_bug6_invoice_number_sequence_handles_fresh_month_concurrently(): void
    {
        InvoiceSequence::query()->delete();
        $numberService = app(InvoiceNumberService::class);

        $n1 = $numberService->nextInvoiceNumber();
        $n2 = $numberService->nextInvoiceNumber();

        $this->assertNotSame($n1, $n2);
        $this->assertMatchesRegularExpression('/^INV-\d{6}-0001$/', $n1);
        $this->assertMatchesRegularExpression('/^INV-\d{6}-0002$/', $n2);
    }

    public function test_bug7_bug8_bug9_bug10_ui_sidebar_layouts_and_line_item_tax_discount(): void
    {
        // Bug 8: Unique sidebar orders
        $orders = array_column(\App\Http\Controllers\Employee\DashboardController::DEFAULT_SIDEBAR_MODULES, 'order');
        $this->assertCount(count($orders), array_unique($orders));

        // Bug 9: Six legacy admin pages use layouts.admin-dashboard with Billing nav
        $preset = PermissionPreset::firstOrFail();
        $employee = User::create([
            'name' => 'Layout Emp',
            'username' => 'layoutemp',
            'email' => 'layout-emp@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'employee',
            'status' => 'active',
            'team_id' => $this->team->id,
        ]);

        foreach ([
            route('admin.permissions.index'),
            route('admin.presets'),
            route('admin.presets.create'),
            route('admin.presets.edit', $preset),
            route('admin.presets.employee', $employee),
            route('admin.installer.recovery'),
        ] as $url) {
            $res = $this->actingAs($this->admin, 'admin')->get($url);
            $res->assertOk();
            $res->assertSee('Billing Dashboard');
        }

        // Bug 7: Employee multi-item invoice create form + Bug 10: line-level discount & tax
        auth()->guard('admin')->logout();
        $empForm = $this->actingAs($employee, 'employee')->get(route('employee.billing.create'));
        $empForm->assertOk();
        $empForm->assertSee('emp-add-item-btn');

        $this->actingAs($employee, 'employee')
            ->post(route('employee.billing.store'), [
                'customer_id' => $this->customer->id,
                'issue_date' => now()->toDateString(),
                'discount_type' => 'fixed',
                'discount_value' => 10,
                'tax_rate' => 10,
                'items' => [
                    [
                        'product_id' => $this->productA->id,
                        'description' => 'Line 1',
                        'quantity' => 2,
                        'unit_price' => 100,
                        'discount_amount' => 20, // taxable = 180
                        'tax_rate' => 10, // line tax = 18 -> line total = 198
                    ],
                    [
                        'description' => 'Line 2',
                        'quantity' => 1,
                        'unit_price' => 112, // line total = 112
                    ],
                ],
            ])->assertRedirect();

        $empInv = Invoice::latest('id')->firstOrFail();
        $this->assertCount(2, $empInv->items);
        // Line 1: 198, Line 2: 112 -> subtotal = 310; invoice discount = 10 -> base = 300; invoice tax 10% = 30 -> grand = 330
        $this->assertEquals(20.00, (float) $empInv->items[0]->discount_amount);
        $this->assertEquals(18.00, (float) $empInv->items[0]->tax_amount);
        $this->assertEquals(198.00, (float) $empInv->items[0]->line_total);
        $this->assertEquals(310.00, (float) $empInv->subtotal);
        $this->assertEquals(330.00, (float) $empInv->grand_total);
    }
}
