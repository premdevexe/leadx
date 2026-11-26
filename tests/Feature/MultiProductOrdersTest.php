<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OrderEditHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PublicOrderShare;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use App\Services\OrderCredentialService;
use App\Services\PaymentService;
use App\Services\PermissionService;
use App\Services\SalesAnalyticsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RefundStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiProductOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Team $team;
    private User $employee;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(PermissionSeeder::class);
        $this->seed(RefundStatusSeeder::class);

        $this->admin = Admin::factory()->create();
        $this->team = Team::factory()->create([
            'team_code' => 'MPO',
            'team_name' => 'Multi Product Team',
            'username' => 'mpoteam',
            'password' => Hash::make('secret123'),
        ]);
        $this->employee = User::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'active',
        ]);

        $this->productA = Product::factory()->create([
            'name' => 'CRM Core Suite',
            'price' => 500.00,
            'minimum_closing_price' => 400.00,
            'duration' => '1y',
            'status' => 'active',
            'api_key_required' => true,
        ]);

        $this->productB = Product::factory()->create([
            'name' => 'WhatsApp Automation Addon',
            'price' => 250.00,
            'minimum_closing_price' => 200.00,
            'duration' => '30d',
            'status' => 'active',
            'api_key_required' => false,
        ]);
    }

    public function test_1_multi_item_order_creation_across_admin_employee_team_and_api_paths(): void
    {
        $itemsPayload = [
            ['product_id' => $this->productA->id, 'quantity' => 2],
            ['product_id' => $this->productB->id, 'quantity' => 3],
        ];

        // 1) Admin Web
        $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Admin Multi Buyer',
            'customer_phone' => '+919900112201',
            'items' => $itemsPayload,
            'closing_value' => 1750.00,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ])->assertSessionHasNoErrors()->assertRedirect();

        // 2) Employee Web
        $this->actingAs($this->employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Employee Multi Buyer',
            'customer_phone' => '+919900112202',
            'items' => $itemsPayload,
            'closing_value' => 1750.00,
        ])->assertSessionHasNoErrors()->assertRedirect();

        // 3) Team Web
        $this->withSession(['team_id' => $this->team->id])->post(route('team.orders.store'), [
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Team Multi Buyer',
            'customer_phone' => '+919900112203',
            'items' => $itemsPayload,
            'closing_value' => 1750.00,
        ])->assertSessionHasNoErrors()->assertRedirect();

        // 4) Unified API (/api/v1/orders) & Employee Panel API (/api/v1/employee/orders)
        Sanctum::actingAs($this->employee, ['*']);
        $apiResp = $this->postJson('/api/v1/orders', [
            'customer_name' => 'API Unified Multi Buyer',
            'customer_phone' => '+919900112204',
            'items' => $itemsPayload,
            'closing_value' => 1750.00,
        ]);
        $apiResp->assertCreated()->assertJsonCount(2, 'data.items');

        $empApiResp = $this->postJson('/api/v1/employee/orders', [
            'customer_name' => 'API Employee Multi Buyer',
            'customer_phone' => '+919900112205',
            'items' => $itemsPayload,
            'closing_value' => 1750.00,
        ]);
        $empApiResp->assertCreated()->assertJsonCount(2, 'data.items');

        foreach ([
            'Admin Multi Buyer',
            'Employee Multi Buyer',
            'Team Multi Buyer',
            'API Unified Multi Buyer',
            'API Employee Multi Buyer',
        ] as $buyer) {
            $order = SalesOrder::with('items')->where('customer_name', $buyer)->firstOrFail();
            $this->assertCount(2, $order->items, "Order for {$buyer} must have 2 item rows.");
            $this->assertEquals('1750.00', number_format((float) $order->product_value, 2, '.', ''));
            $this->assertSame($this->productA->id, $order->product_id);
            $this->assertSame($this->productA->name, $order->product_name_snapshot);

            $lineA = $order->items->firstWhere('product_id', $this->productA->id);
            $lineB = $order->items->firstWhere('product_id', $this->productB->id);
            $this->assertSame(2, $lineA->quantity);
            $this->assertSame('1000.00', number_format((float) $lineA->line_total, 2, '.', ''));
            $this->assertSame(3, $lineB->quantity);
            $this->assertSame('750.00', number_format((float) $lineB->line_total, 2, '.', ''));
        }

        // Verify web show pages & public share page render multi-item table cleanly
        $sampleOrder = SalesOrder::where('customer_name', 'Admin Multi Buyer')->firstOrFail();
        $this->actingAs($this->admin, 'admin')->get(route('admin.orders.show', $sampleOrder))
            ->assertOk()->assertSee($this->productA->name)->assertSee($this->productB->name);
        $this->actingAs($this->employee, 'employee')->get(route('employee.orders.show', $sampleOrder))
            ->assertOk()->assertSee($this->productA->name)->assertSee($this->productB->name);
        $this->withSession(['team_id' => $this->team->id])->get(route('team.orders.show', $sampleOrder))
            ->assertOk()->assertSee($this->productA->name)->assertSee($this->productB->name);

        $this->actingAs($this->admin, 'admin')->post(route('admin.orders.public-share.store', $sampleOrder));
        $share = PublicOrderShare::where('order_id', $sampleOrder->id)->firstOrFail();
        $this->get(route('public.orders.show', ['token' => $share->token]))
            ->assertOk()->assertSee($this->productA->name)->assertSee($this->productB->name);

        // 5) Admin via Unified API (/api/v1/orders)
        Sanctum::actingAs($this->admin, ['*']);
        $this->postJson('/api/v1/orders', [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'API Admin Multi Buyer',
            'items' => $itemsPayload,
            'closing_value' => 1750.00,
        ])->assertCreated()->assertJsonCount(2, 'data.items');
    }

    public function test_2_client_submitted_prices_line_totals_and_product_value_are_ignored(): void
    {
        $this->actingAs($this->employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Forged Price Buyer',
            'customer_phone' => '+919900112210',
            'product_value' => 1.00,
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 2,
                    'unit_price' => 1.00,
                    'line_total' => 2.00,
                ],
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 3,
                    'unit_price' => 1.00,
                    'line_total' => 3.00,
                ],
            ],
            // Total real list price is 2*500 + 3*250 = 1750; floor = 2*400 + 3*200 = 1400
            'closing_value' => 1500.00,
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::with('items')->where('customer_name', 'Forged Price Buyer')->firstOrFail();
        $this->assertSame('1750.00', number_format((float) $order->product_value, 2, '.', ''));
        $this->assertSame('250.00', number_format((float) $order->discount_amount, 2, '.', ''));
        $this->assertSame('1500.00', number_format((float) $order->closing_value, 2, '.', ''));
        $this->assertSame('500.00', number_format((float) $order->items->firstWhere('product_id', $this->productA->id)->unit_price, 2, '.', ''));
        $this->assertSame('1000.00', number_format((float) $order->items->firstWhere('product_id', $this->productA->id)->line_total, 2, '.', ''));
    }

    public function test_3_duplicate_product_ids_merge_quantities_into_single_line(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Duplicate Line Buyer',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2],
                ['product_id' => $this->productA->id, 'quantity' => 3],
            ],
            'closing_value' => 2500.00,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::with('items')->where('customer_name', 'Duplicate Line Buyer')->firstOrFail();
        $this->assertCount(1, $order->items);
        $line = $order->items->first();
        $this->assertSame(5, $line->quantity);
        $this->assertSame('2500.00', number_format((float) $line->line_total, 2, '.', ''));
        $this->assertSame('2500.00', number_format((float) $order->product_value, 2, '.', ''));
    }

    public function test_4_quantity_validation_rejects_zero_negative_and_non_integer_across_paths(): void
    {
        foreach ([0, -1, 1.5, 'abc'] as $badQty) {
            $items = [['product_id' => $this->productA->id, 'quantity' => $badQty]];

            $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), [
                'team_id' => $this->team->id,
                'assigned_employee_id' => $this->employee->id,
                'customer_name' => 'Bad Qty Admin',
                'items' => $items,
                'closing_value' => 500,
                'payment_status' => 'pending',
                'order_status' => 'pending',
            ])->assertSessionHasErrors('items.0.quantity');

            $this->actingAs($this->employee, 'employee')->post(route('employee.orders.store'), [
                'customer_name' => 'Bad Qty Employee',
                'items' => $items,
                'closing_value' => 500,
            ])->assertSessionHasErrors('items.0.quantity');

            $this->withSession(['team_id' => $this->team->id])->post(route('team.orders.store'), [
                'assigned_employee_id' => $this->employee->id,
                'customer_name' => 'Bad Qty Team',
                'items' => $items,
                'closing_value' => 500,
            ])->assertSessionHasErrors('items.0.quantity');

            Sanctum::actingAs($this->employee, ['*']);
            $this->postJson('/api/v1/employee/orders', [
                'customer_name' => 'Bad Qty API',
                'items' => $items,
                'closing_value' => 500,
            ])->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);
        }

        $this->assertSame(0, SalesOrder::count());
    }

    public function test_5_migration_backfills_legacy_single_product_orders_into_one_item_row(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);

        $orderId = DB::table('sales_orders')->insertGetId([
            'order_number' => 'MPO-LEGACY-001',
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Legacy Backfill Customer',
            'customer_phone' => '+919900112299',
            'product_id' => $this->productA->id,
            'product_name' => 'Legacy CRM Suite',
            'product_name_snapshot' => 'Legacy CRM Suite Snapshot',
            'product_price_snapshot' => 799.00,
            'product_duration_snapshot' => '1y',
            'minimum_closing_price_snapshot' => 600.00,
            'api_key_required_snapshot' => 1,
            'duration' => '1y',
            'product_value' => 799.00,
            'discount_amount' => 99.00,
            'closing_value' => 700.00,
            'amount_received' => 0.00,
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('migrate', ['--force' => true]);

        $item = SalesOrderItem::where('order_id', $orderId)->firstOrFail();
        $this->assertSame($this->productA->id, $item->product_id);
        $this->assertSame('Legacy CRM Suite Snapshot', $item->product_name_snapshot);
        $this->assertSame('799.00', number_format((float) $item->unit_price, 2, '.', ''));
        $this->assertSame('799.00', number_format((float) $item->line_total, 2, '.', ''));
        $this->assertSame(1, $item->quantity);
        $this->assertSame('1y', $item->product_duration_snapshot);
        $this->assertTrue((bool) $item->api_key_required_snapshot);
    }

    public function test_6_basket_floor_totals_discount_calculation_and_closing_approval_rules(): void
    {
        // Basket: 2 * A(500, floor 400) + 1 * B(250, floor 200) => list 1250, floor 1000
        $items = [
            ['product_id' => $this->productA->id, 'quantity' => 2],
            ['product_id' => $this->productB->id, 'quantity' => 1],
        ];

        // Employee below combined floor (999 < 1000) -> rejected
        $this->actingAs($this->employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Below Floor Multi',
            'items' => $items,
            'closing_value' => 999.00,
        ])->assertSessionHasErrors('closing_value');

        // Employee discounted closing >= floor & < list (1100) -> Requested + discount = 150
        $this->actingAs($this->employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Discounted Multi Buyer',
            'customer_phone' => '+919900112220',
            'items' => $items,
            'closing_value' => 1100.00,
        ])->assertSessionHasNoErrors();

        $discountedOrder = SalesOrder::where('customer_name', 'Discounted Multi Buyer')->firstOrFail();
        $this->assertSame('1250.00', number_format((float) $discountedOrder->product_value, 2, '.', ''));
        $this->assertSame('150.00', number_format((float) $discountedOrder->discount_amount, 2, '.', ''));
        $this->assertSame('Requested', $discountedOrder->closing_value_status);

        // Employee at full list price (1250) -> Accepted + discount = 0
        $this->actingAs($this->employee, 'employee')->post(route('employee.orders.store'), [
            'customer_name' => 'Full Price Multi Buyer',
            'customer_phone' => '+919900112221',
            'items' => $items,
            'closing_value' => 1250.00,
        ])->assertSessionHasNoErrors();

        $fullOrder = SalesOrder::where('customer_name', 'Full Price Multi Buyer')->firstOrFail();
        $this->assertSame('Accepted', $fullOrder->closing_value_status);
        $this->assertSame('0.00', number_format((float) $fullOrder->discount_amount, 2, '.', ''));

        // Admin can close below floor (900 < 1000) -> Accepted
        $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Admin Floor Bypass Buyer',
            'items' => $items,
            'closing_value' => 900.00,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ])->assertSessionHasNoErrors();

        $adminOrder = SalesOrder::where('customer_name', 'Admin Floor Bypass Buyer')->firstOrFail();
        $this->assertSame('350.00', number_format((float) $adminOrder->discount_amount, 2, '.', ''));
        $this->assertSame('Accepted', $adminOrder->closing_value_status);
    }

    public function test_7_paid_order_refuses_item_edits_while_pending_order_allows_edits_and_writes_audit_history(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Editable Pending Order',
            'customer_phone' => '+919900112230',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
            'closing_value' => 500.00,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::where('customer_name', 'Editable Pending Order')->firstOrFail();

        // Admin edits pending order items (adds Product B and updates Product A qty) -> allowed + audit logged
        $this->actingAs($this->admin, 'admin')->put(route('admin.orders.update', $order), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Editable Pending Order',
            'customer_phone' => '+919900112230',
            'duration' => '1y',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2],
                ['product_id' => $this->productB->id, 'quantity' => 1],
            ],
            'closing_value' => 1250.00,
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'edit_reason' => 'Customer expanded seat count and added WhatsApp module',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order->refresh()->load('items');
        $this->assertCount(2, $order->items);
        $this->assertSame('1250.00', number_format((float) $order->product_value, 2, '.', ''));
        $this->assertGreaterThan(0, OrderEditHistory::where('order_id', $order->id)->count());

        // Record & verify a payment -> order items become locked
        $payment = Payment::create([
            'order_id' => $order->id,
            'customer_name' => $order->customer_name,
            'amount' => 1250.00,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
        ]);
        app(PaymentService::class)->verify($payment, $this->admin->id);

        // Attempt item modification after verified payment -> must be rejected on `items`
        $this->actingAs($this->admin, 'admin')->put(route('admin.orders.update', $order), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Editable Pending Order',
            'customer_phone' => '+919900112230',
            'duration' => '1y',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1],
            ],
            'closing_value' => 500.00,
            'payment_status' => 'paid',
            'order_status' => 'active',
            'edit_reason' => 'Should be blocked after verified payment',
        ])->assertSessionHasErrors('items');

        $this->assertCount(2, $order->fresh()->items);

        // Scalar edit without modifying items still succeeds on locked order
        $this->actingAs($this->admin, 'admin')->put(route('admin.orders.update', $order), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Updated Name After Payment',
            'customer_phone' => '+919900112230',
            'duration' => '1y',
            'closing_value' => 1250.00,
            'payment_status' => 'paid',
            'order_status' => 'active',
            'edit_reason' => 'Corrected company contact name',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Updated Name After Payment', $order->fresh()->customer_name);
    }

    public function test_8_full_payment_activates_one_subscription_per_line_with_per_product_expiry_dates(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Dual Sub Customer',
            'customer_phone' => '+919900112240',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1], // duration 1y
                ['product_id' => $this->productB->id, 'quantity' => 2], // duration 30d
            ],
            'closing_value' => 1000.00,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::with('items')->where('customer_name', 'Dual Sub Customer')->firstOrFail();
        $itemA = $order->items->firstWhere('product_id', $this->productA->id);
        $itemB = $order->items->firstWhere('product_id', $this->productB->id);

        // Product A requires an API key before subscription activation
        app(OrderCredentialService::class)->store($order, 'sk_test_dual_sub_123456', $this->admin, $itemA->id);

        $payment = Payment::create([
            'order_id' => $order->id,
            'customer_name' => $order->customer_name,
            'amount' => 1000.00,
            'payment_method' => 'upi',
            'payment_status' => 'pending',
        ]);
        app(PaymentService::class)->verify($payment, $this->admin->id);

        $subs = Subscription::where('order_id', $order->id)->get();
        $this->assertCount(2, $subs);

        $subA = $subs->firstWhere('order_item_id', $itemA->id);
        $subB = $subs->firstWhere('order_item_id', $itemB->id);

        $this->assertNotNull($subA);
        $this->assertNotNull($subB);
        $this->assertSame('active', $subA->status);
        $this->assertSame('active', $subB->status);
        $this->assertTrue($subA->expires_at->greaterThan($subB->expires_at->copy()->addMonths(6)));
    }

    public function test_9_invoice_created_from_multi_item_order_contains_all_lines_and_renders_pdf(): void
    {
        $customer = Customer::create([
            'name' => 'Invoice Multi Buyer',
            'email' => 'invoice-multi@example.com',
            'phone' => '+919900112250',
            'team_id' => $this->team->id,
            'status' => 'lead',
        ]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Invoice Multi Buyer',
            'customer_email' => 'invoice-multi@example.com',
            'customer_phone' => '+919900112250',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 2], // 1000
                ['product_id' => $this->productB->id, 'quantity' => 1], // 250
            ],
            'closing_value' => 1150.00, // discount = 100
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::with('items')->where('customer_name', 'Invoice Multi Buyer')->firstOrFail();

        // Admin & Employee invoice create forms prefill from ?order_id=
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.invoices.create', ['order_id' => $order->id]))
            ->assertOk()
            ->assertSee($this->productA->name)
            ->assertSee($this->productB->name);

        app(PermissionService::class)->setEmployeeOverride($this->employee, 'billing.invoices.create', true);
        $this->actingAs($this->employee, 'employee')
            ->get(route('employee.billing.create', ['order_id' => $order->id]))
            ->assertOk()
            ->assertSee($this->productA->name)
            ->assertSee($this->productB->name);

        // Creating invoice via InvoiceService from order_id auto-copies all lines & discount
        $invoice = app(InvoiceService::class)->createInvoice([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'issue_date' => now()->toDateString(),
            'status' => 'sent',
            'tax_rate' => 0,
        ], $this->admin);

        $invoice->load('items');
        $this->assertCount(2, $invoice->items);
        $this->assertSame('1250.00', number_format((float) $invoice->subtotal, 2, '.', ''));
        $this->assertSame('100.00', number_format((float) $invoice->discount_amount, 2, '.', ''));
        $this->assertSame('1150.00', number_format((float) $invoice->grand_total, 2, '.', ''));

        $pdf = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.billing.invoices.pdf', $invoice));
        $pdf->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_10_legacy_employee_api_single_product_compatibility_and_analytics_non_double_counting(): void
    {
        Sanctum::actingAs($this->employee, ['*']);

        // Legacy payload: single product_id only (no items array)
        $response = $this->postJson('/api/v1/employee/orders', [
            'customer_name' => 'Legacy API Buyer',
            'customer_phone' => '+919900112260',
            'product_id' => $this->productA->id,
            'closing_value' => 500.00,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.product_id', $this->productA->id)
            ->assertJsonPath('data.product_name', $this->productA->name)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.product_id', $this->productA->id)
            ->assertJsonPath('data.items.0.quantity', 1);

        // Now create a multi-item order and verify SalesAnalyticsService does NOT double-count overview
        $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Analytics Multi Buyer',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1], // 500
                ['product_id' => $this->productB->id, 'quantity' => 1], // 250
            ],
            'closing_value' => 750.00,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ])->assertSessionHasNoErrors();

        $analytics = app(SalesAnalyticsService::class);
        $overview = $analytics->overview([]);
        $this->assertSame(2, $overview['total_orders']);
        // 500 (legacy single) + 750 (multi) = 1250 exactly, NOT 500 + 500 + 250 + 750!
        $this->assertEquals(1250.00, (float) $overview['total_closing_value']);

        $productsReport = collect($analytics->productReport([]));
        $rowA = $productsReport->firstWhere('product_id', $this->productA->id);
        $rowB = $productsReport->firstWhere('product_id', $this->productB->id);
        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);
        $this->assertSame(2, $rowA['orders']);
        $this->assertSame(1, $rowB['orders']);
    }

    public function test_11_per_item_subscription_duration_auto_fetches_and_allows_per_line_override(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.orders.store'), [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Per Line Duration Buyer',
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 1], // auto-fetches 1y
                ['product_id' => $this->productB->id, 'quantity' => 1, 'duration' => '6m'], // overrides 30d -> 6m
            ],
            'closing_value' => 750.00,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ])->assertSessionHasNoErrors();

        $order = SalesOrder::with('items')->where('customer_name', 'Per Line Duration Buyer')->firstOrFail();
        $lineA = $order->items->firstWhere('product_id', $this->productA->id);
        $lineB = $order->items->firstWhere('product_id', $this->productB->id);

        $this->assertSame('1y', $lineA->product_duration_snapshot);
        $this->assertSame('6m', $lineB->product_duration_snapshot);
    }
}
