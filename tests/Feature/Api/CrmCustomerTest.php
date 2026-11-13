<?php

namespace Tests\Feature\Api;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Services\CrmCustomerService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    // Repeat purchases update the existing CRM customer — no duplicates.
    public function test_repeat_purchase_updates_existing_customer(): void
    {
        $team = Team::factory()->create(['team_code' => 'CRM']);

        $order1 = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'customer_name' => 'Ada Lovelace',
            'customer_phone' => '+1 555-0100',
            'closing_value' => '100.00',
        ]);
        $p1 = Payment::factory()->create(['order_id' => $order1->id, 'amount' => '100.00']);
        app(PaymentService::class)->verify($p1, $this->admin->id);

        $order2 = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'customer_name' => 'Ada Lovelace',
            'customer_phone' => '+1-555-0100', // formatted differently, same number
            'closing_value' => '50.00',
        ]);
        $p2 = Payment::factory()->create(['order_id' => $order2->id, 'amount' => '50.00']);
        app(PaymentService::class)->verify($p2, $this->admin->id);

        $customers = Customer::where('team_id', $team->id)->get();
        $this->assertCount(1, $customers);

        $customer = $customers->first();
        $this->assertSame('paying', $customer->status);
        $this->assertSame(2, (int) $customer->purchases_count);
        $this->assertSame('150.00', (string) $customer->total_purchased);
        $this->assertNotNull($customer->last_purchase_at);
    }

    // Unpaid/pending orders never create or promote a CRM customer.
    public function test_unpaid_order_does_not_create_customer(): void
    {
        $team = Team::factory()->create(['team_code' => 'CRQ']);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'customer_name' => 'Unpaid Enquiry',
            'customer_phone' => '+1 555-0200',
            'payment_status' => 'pending',
        ]);

        // No verified payment at all.
        $this->assertSame(0, Customer::where('team_id', $team->id)->count());

        // The service-level guard: only verified purchases may upsert.
        $crm = app(CrmCustomerService::class);
        $this->assertNull($crm->match($order));

        // Even a pending payment row must not create a customer.
        Payment::factory()->create([
            'order_id' => $order->id,
            'amount' => '10.00',
            'payment_status' => 'pending',
            'verified_by_admin_id' => null,
        ]);

        $this->assertSame(0, Customer::where('team_id', $team->id)->count());
    }

    // Matching falls back to the phone+email pair when phones differ.
    public function test_customer_match_uses_phone_then_pair(): void
    {
        $team = Team::factory()->create(['team_code' => 'CRP']);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'customer_name' => 'Grace Hopper',
            'customer_phone' => '+1 555-0300',
            'customer_email' => 'grace@example.com',
            'closing_value' => '100.00',
        ]);

        $p = Payment::factory()->create(['order_id' => $order->id, 'amount' => '100.00']);
        app(PaymentService::class)->verify($p, $this->admin->id);

        $created = Customer::where('team_id', $team->id)->first();
        $this->assertSame('15550300', $created->normalized_phone);
        $this->assertSame('grace@example.com', $created->normalized_email);

        // Same phone, different email → still the same customer (phone wins).
        $crm = app(CrmCustomerService::class);
        $order2 = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'customer_name' => 'Grace Hopper',
            'customer_phone' => '+1 555-0300',
            'customer_email' => 'other@example.com',
        ]);

        $matched = $crm->match($order2);
        $this->assertSame($created->id, $matched->id);
    }
}
