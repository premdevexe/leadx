<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\SalesAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

/**
 * End-to-end dynamic product integration: a catalog product created through
 * the real admin workflow drives order creation, payment verification,
 * subscription activation (with duration-snapshot expiry), CRM creation and
 * analytics — no hardcoded product values anywhere.
 */
class ProductIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected Admin $admin;
    protected Team $team;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->team = Team::factory()->create(['team_code' => 'INT']);
        $this->employee = User::factory()->create(['team_id' => $this->team->id]);
    }

    /**
     * Create a product through the actual admin panel workflow (HTTP).
     */
    protected function createProductViaAdmin(array $attrs): Product
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), array_merge([
                'name' => 'Dynamic Offer',
                'price' => '249.99',
                'duration' => '6m',
                'status' => 'active',
            ], $attrs))
            ->assertRedirect(route('admin.products'));

        return Product::where('name', 'Dynamic Offer')->first();
    }

    protected function createOrderViaApi(array $attrs = []): SalesOrder
    {
        $response = $this->postJson('/api/v1/orders', array_merge([
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Ada Lovelace',
            'customer_phone' => '+1 555-0900',
            'closing_value' => 0,
        ], $attrs), $this->actingAsToken($this->employeeToken($this->employee)));

        $response->assertCreated();

        return SalesOrder::findOrFail($response->json('data.id'));
    }

    protected function verifyFullPayment(SalesOrder $order, string $amount = null): Payment
    {
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => $amount ?? $order->amountDue(),
            'payment_status' => 'pending',
            'source' => 'manual',
            'verified_by_admin_id' => null,
            'verification_source' => null,
            'verified_at' => null,
        ]);

        app(PaymentService::class)->verify($payment, $this->admin->id, 'integration check');

        return $payment->fresh();
    }

    // 1. A product created through the admin workflow appears in order selection.
    public function test_new_product_appears_in_order_selection(): void
    {
        $product = $this->createProductViaAdmin([]);
        $this->assertNotNull($product);

        // Active catalog endpoint (database-driven, no hardcoding).
        $response = $this->getJson('/api/v1/products', $this->actingAsToken($this->employeeToken($this->employee)));
        $response->assertOk()->assertJsonPath('products.0.name', 'Dynamic Offer');
        $this->assertSame('active', Product::find($product->id)->status);

        // Admin order form offers it.
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.create'))
            ->assertOk()
            ->assertSee('Dynamic Offer');
    }

    // 2. Order price comes from the database, not the client.
    public function test_order_price_and_duration_are_fetched_from_database(): void
    {
        $product = $this->createProductViaAdmin([]);

        // Client submits bogus values alongside the product id.
        $order = $this->createOrderViaApi([
            'product_id' => $product->id,
            'product_name' => 'Hacked Name',
            'product_value' => 1,
            'duration' => '3d',
        ]);

        $this->assertSame($product->id, $order->product_id);
        $this->assertSame('Dynamic Offer', $order->product_name);
        $this->assertSame('Dynamic Offer', $order->product_name_snapshot);
        $this->assertSame('249.99', (string) $order->product_value);
        $this->assertSame('249.99', (string) $order->product_price_snapshot);
        $this->assertSame('6m', $order->duration);
        $this->assertSame('6m', $order->product_duration_snapshot);
        $this->assertSame('INT-'.now()->format('Y').'-000001', $order->order_number);
        $this->assertSame($this->employee->id, $order->assigned_employee_id);
    }

    // 3. Inactive products cannot be used for new orders.
    public function test_inactive_product_cannot_be_used_for_new_orders(): void
    {
        $product = $this->createProductViaAdmin([]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.products.update', $product), [
                'name' => 'Dynamic Offer',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.products'));

        $this->postJson('/api/v1/orders', [
            'team_id' => $this->team->id,
            'assigned_employee_id' => $this->employee->id,
            'customer_name' => 'Ada',
            'product_id' => $product->id,
        ], $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);

        $this->assertSame(0, SalesOrder::count());
    }

    // 4. Snapshots survive later product price/duration changes.
    public function test_order_snapshots_survive_product_edits(): void
    {
        $product = $this->createProductViaAdmin([]);
        $order = $this->createOrderViaApi(['product_id' => $product->id]);

        $this->assertSame('249.99', (string) $order->product_value);

        // Admin edits the catalog afterwards.
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.products.update', $product), [
                'name' => 'Dynamic Offer',
                'price' => '999.99',
                'duration' => '1y',
            ])
            ->assertRedirect(route('admin.products'));

        $fresh = $order->fresh();
        $this->assertSame('249.99', (string) $fresh->product_value);
        $this->assertSame('249.99', (string) $fresh->product_price_snapshot);
        $this->assertSame('6m', $fresh->duration);
        $this->assertSame('6m', $fresh->product_duration_snapshot);
        $this->assertSame('Dynamic Offer', $fresh->product_name);
        $this->assertSame('Dynamic Offer', $fresh->product_name_snapshot);
    }

    // 5. Payment verification activates; CRM customer is created only on verified purchase.
    public function test_verified_purchase_creates_crm_customer_and_links_it(): void
    {
        $product = $this->createProductViaAdmin([]);
        $order = $this->createOrderViaApi([
            'product_id' => $product->id,
            'closing_value' => '249.99',
        ]);

        // No customer before verification.
        $this->assertSame(0, Customer::count());

        $this->verifyFullPayment($order);

        $customer = Customer::where('team_id', $this->team->id)->first();
        $this->assertNotNull($customer);
        $this->assertSame('paying', $customer->status);
        $this->assertSame(1, (int) $customer->purchases_count);
        $this->assertSame('249.99', (string) $customer->total_purchased);
        $this->assertSame($customer->id, $order->fresh()->payments()->first()->customer_id);
    }

    // 6. Unverified payments never create customers or activate subscriptions.
    public function test_unverified_payment_never_creates_customer_or_subscription(): void
    {
        $product = $this->createProductViaAdmin([]);
        $order = $this->createOrderViaApi(['product_id' => $product->id, 'closing_value' => '249.99']);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => '249.99',
            'payment_status' => 'pending',
        ]);

        $this->assertSame(0, Customer::count());
        $this->assertNull($order->fresh()->subscription);

        // A pending payment row must not promote anything.
        $this->assertSame('pending', $payment->payment_status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    // 7. Subscription expiry is calculated from the saved duration snapshot.
    public function test_subscription_expiry_uses_saved_duration_snapshot(): void
    {
        Carbon::setTestNow('2026-03-15 10:00:00');

        $product = $this->createProductViaAdmin([]);
        $order = $this->createOrderViaApi(['product_id' => $product->id, 'closing_value' => '249.99']);
        $this->verifyFullPayment($order);

        $subscription = $order->fresh()->subscription;
        $this->assertSame('active', $subscription->status);
        $this->assertSame('6m', $order->fresh()->duration);
        // 2026-03-15 + 6 months = 2026-09-15, day-accurate and clamped-safe.
        $this->assertSame('2026-09-15', $subscription->expires_at->format('Y-m-d'));
        $this->assertSame('249.99', (string) $subscription->price_snapshot);
        $this->assertSame($product->id, $subscription->product_id);

        Carbon::setTestNow();
    }

    // 8. Duplicate verification is a no-op (idempotent activation).
    public function test_duplicate_verification_never_duplicates_activation(): void
    {
        $product = $this->createProductViaAdmin([]);
        $order = $this->createOrderViaApi(['product_id' => $product->id, 'closing_value' => '249.99']);
        $payment = $this->verifyFullPayment($order);

        $subscriptionCount = Subscription::where('order_id', $order->id)->count();
        $this->assertSame(1, $subscriptionCount);

        // Verify the same (already verified) payment again.
        app(PaymentService::class)->verify($payment->fresh(), $this->admin->id, 'second check');

        $this->assertSame(1, Subscription::where('order_id', $order->id)->count());
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    // 9. Repeat purchases stay on the same CRM customer.
    public function test_repeat_purchase_stays_on_same_customer(): void
    {
        $product = $this->createProductViaAdmin([]);

        $order1 = $this->createOrderViaApi([
            'product_id' => $product->id,
            'customer_name' => 'Ada Lovelace',
            'customer_phone' => '+1 555-0900',
            'closing_value' => '249.99',
        ]);
        $this->verifyFullPayment($order1);

        $order2 = $this->createOrderViaApi([
            'product_id' => $product->id,
            'customer_name' => 'Ada Lovelace',
            'customer_phone' => '+1-555-0900', // same number, other format
            'closing_value' => '249.99',
        ]);
        $this->verifyFullPayment($order2);

        $customers = Customer::where('team_id', $this->team->id)->get();
        $this->assertCount(1, $customers);
        $this->assertSame(2, (int) $customers->first()->purchases_count);
        $this->assertSame('499.98', (string) $customers->first()->total_purchased);
    }

    // 10. Analytics picks up the new product automatically (catalog-driven).
    public function test_product_analytics_include_new_product_automatically(): void
    {
        $product = $this->createProductViaAdmin([]);
        $order = $this->createOrderViaApi(['product_id' => $product->id, 'closing_value' => '249.99']);
        $this->verifyFullPayment($order);

        $report = app(SalesAnalyticsService::class)->productReport();
        $row = collect($report)->firstWhere('product', 'Dynamic Offer');
        $this->assertNotNull($row);
        $this->assertSame(1, $row['orders']);
        $this->assertSame('249.99', $row['collected_revenue']);
        $this->assertSame(1, $row['active_subscriptions']);
        $this->assertSame(1, $row['active_customers']);
        $this->assertSame('0.00', $row['pending_collection']);

        // Brand-new product (no orders yet) also appears, zero-filled.
        Product::create(['name' => 'Brand New', 'price' => '99.00', 'duration' => '1y', 'status' => 'active']);
        $row2 = collect(app(SalesAnalyticsService::class)->productReport())->firstWhere('product', 'Brand New');
        $this->assertNotNull($row2);
        $this->assertSame(0, $row2['orders']);
        $this->assertSame('0.00', $row2['collected_revenue']);

        // Admin-only endpoint.
        $this->getJson('/api/v1/admin/analytics/products', $this->actingAsToken($this->employeeToken($this->employee)))
            ->assertStatus(403);

        $this->getJson('/api/v1/admin/analytics/products', $this->actingAsToken($this->adminToken($this->admin)))
            ->assertOk()
            ->assertJsonPath('0.product', 'Brand New');
    }

    // 11. Employee attribution: orders belong to the assigned employee and team.
    public function test_orders_are_attributed_to_employee_and_team(): void
    {
        $product = $this->createProductViaAdmin([]);
        $order = $this->createOrderViaApi([
            'product_id' => $product->id,
            'assigned_employee_id' => $this->employee->id,
            'closing_value' => '249.99',
        ]);

        $this->assertSame($this->team->id, $order->team_id);
        $this->assertSame($this->employee->id, $order->assigned_employee_id);
        $this->assertSame($this->team->team_code, $order->team_code_snapshot);

        // A foreign team cannot access it.
        $foreignTeam = Team::factory()->create(['team_code' => 'FRN']);
        $this->getJson("/api/v1/orders/{$order->id}", $this->actingAsToken($this->teamToken($foreignTeam)))
            ->assertStatus(422);
    }
}