<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrmAndPaymentFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_admin_can_record_and_verify_payment_on_order_show_page(): void
    {
        $team = Team::factory()->create(['team_code' => 'PAY']);
        $product = Product::factory()->create(['name' => 'Monthly Pro', 'price' => 150.00, 'duration' => '30d']);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'customer_name' => 'John Doe',
            'product_id' => $product->id,
            'product_name' => $product->name,
            'closing_value' => '150.00',
            'amount_received' => '0.00',
            'payment_status' => 'pending',
        ]);

        $admin = $this->admin();

        // 1. Order detail page renders payments card
        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Payments', false)
            ->assertSee('Record a Payment Receipt');

        // 2. Admin records a manual payment
        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.payments.store', $order), [
                'amount' => '150.00',
                'payment_method' => 'bank_transfer',
                'admin_notes' => 'Received via wire transfer',
            ])
            ->assertRedirect();

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('pending', $payment->payment_status);

        // 3. Admin verifies the payment
        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.payments.verify', [$order, $payment]))
            ->assertRedirect();

        $freshPayment = $payment->fresh();
        $this->assertSame('paid', $freshPayment->payment_status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('150.00', (string) $order->fresh()->amount_received);

        // 4. Verification activated subscription and created CRM customer
        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'status' => 'paying',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'order_id' => $order->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_reject_payment_on_order_show_page(): void
    {
        $team = Team::factory()->create(['team_code' => 'REJ']);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'closing_value' => '200.00',
            'payment_status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => '200.00',
            'payment_status' => 'pending',
        ]);

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.orders.payments.reject', [$order, $payment]))
            ->assertRedirect();

        $this->assertSame('failed', $payment->fresh()->payment_status);
    }

    public function test_admin_can_view_crm_customers_and_customer_details(): void
    {
        $team = Team::factory()->create(['team_code' => 'CRM']);
        $customer = Customer::factory()->create([
            'name' => 'Jane Smith',
            'phone' => '+15551234567',
            'email' => 'jane@example.com',
            'status' => 'paying',
            'team_id' => $team->id,
            'purchases_count' => 2,
            'total_purchased' => '300.00',
            'last_purchase_at' => now(),
        ]);

        $enquiry = Customer::factory()->create([
            'name' => 'Unverified Lead',
            'status' => 'enquiry',
            'team_id' => $team->id,
        ]);

        $admin = $this->admin();

        // 1. CRM index only shows paying customers who completed verified purchases
        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers'))
            ->assertOk()
            ->assertSee('Jane Smith')
            ->assertDontSee('Unverified Lead');

        // 2. Search filter
        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers', ['search' => 'Jane']))
            ->assertOk()
            ->assertSee('Jane Smith');

        // 3. Customer detail page renders profile, purchases, and order history
        $this->actingAs($admin, 'admin')
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Customer Info')
            ->assertSee('Jane Smith')
            ->assertSee('Subscriptions', false)
            ->assertSee('Order', false);
    }
}
