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
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Api\Concerns\ProvidesApiTokens;
use Tests\TestCase;

class Phase10SalesAnalyticsAndReportsTest extends TestCase
{
    use RefreshDatabase;
    use ProvidesApiTokens;

    protected Admin $admin;
    protected Team $teamA;
    protected Team $teamB;
    protected User $employeeA;
    protected User $employeeB;
    protected Product $productPro;
    protected Product $productElite;
    protected SalesAnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create(['username' => 'admin_analyst']);
        $this->teamA = Team::factory()->create(['team_code' => 'TMA', 'team_name' => 'Team Alpha']);
        $this->teamB = Team::factory()->create(['team_code' => 'TMB', 'team_name' => 'Team Beta']);

        $this->employeeA = User::factory()->create([
            'role' => 'employee',
            'team_id' => $this->teamA->id,
            'username' => 'emp_alpha',
        ]);

        $this->employeeB = User::factory()->create([
            'role' => 'employee',
            'team_id' => $this->teamB->id,
            'username' => 'emp_beta',
        ]);

        $this->productPro = Product::create([
            'name' => 'Pro AI 1 Month',
            'price' => 100.00,
            'duration' => '1m',
            'status' => 'active',
        ]);

        $this->productElite = Product::create([
            'name' => 'Elite AI 1 Year',
            'price' => 1000.00,
            'duration' => '1y',
            'status' => 'active',
        ]);

        $this->analytics = app(SalesAnalyticsService::class);
    }

    /**
     * Helper to create an order with snapshots and optional payment.
     */
    protected function createOrder(
        Team $team,
        User $employee,
        Product $product,
        float $productValue,
        float $closingValue,
        ?float $verifiedPayment = null,
        ?float $pendingPayment = null,
        ?float $refundedPayment = null,
        ?string $createdAt = null
    ): SalesOrder {
        $order = SalesOrder::create([
            'order_number' => $team->team_code . '-' . now()->format('Y') . '-' . str_pad((string) rand(1, 99999), 6, '0', STR_PAD_LEFT),
            'team_id' => $team->id,
            'team_code_snapshot' => $team->team_code,
            'assigned_employee_id' => $employee->id,
            'customer_name' => 'Test Customer',
            'customer_phone' => '+1555000' . rand(1000, 9999),
            'customer_email' => 'cust' . rand(100, 999) . '@example.com',
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_name_snapshot' => $product->name,
            'product_price_snapshot' => $product->price,
            'product_duration_snapshot' => $product->duration,
            'duration' => $product->duration,
            'product_value' => $productValue,
            'discount_amount' => max(0, $productValue - $closingValue),
            'closing_value' => $closingValue,
            'amount_received' => 0,
            'payment_status' => 'pending',
            'order_status' => 'active',
        ]);

        if ($createdAt) {
            $order->timestamps = false;
            $order->created_at = Carbon::parse($createdAt);
            $order->save();
        }

        if ($verifiedPayment !== null && $verifiedPayment > 0) {
            $p = Payment::create([
                'order_id' => $order->id,
                'source' => 'manual',
                'payment_method' => 'upi',
                'amount' => $verifiedPayment,
                'currency' => 'USD',
                'payment_status' => 'pending',
                'created_at' => $createdAt ? Carbon::parse($createdAt) : now(),
            ]);

            app(PaymentService::class)->verify($p, $this->admin->id);

            if ($createdAt) {
                $p->forceFill([
                    'verified_at' => Carbon::parse($createdAt),
                    'created_at' => Carbon::parse($createdAt),
                ])->save();
            }
        }

        if ($pendingPayment !== null && $pendingPayment > 0) {
            Payment::create([
                'order_id' => $order->id,
                'source' => 'manual',
                'payment_method' => 'bank_transfer',
                'amount' => $pendingPayment,
                'currency' => 'USD',
                'payment_status' => 'pending',
                'created_at' => $createdAt ? Carbon::parse($createdAt) : now(),
            ]);
        }

        if ($refundedPayment !== null && $refundedPayment > 0) {
            Payment::create([
                'order_id' => $order->id,
                'source' => 'manual',
                'payment_method' => 'upi',
                'amount' => $refundedPayment,
                'currency' => 'USD',
                'payment_status' => 'refunded',
                'verified_by_admin_id' => $this->admin->id,
                'verification_source' => 'admin',
                'verified_at' => now(),
                'created_at' => now(),
            ]);
        }

        SalesAnalyticsService::invalidateCache();

        return $order->fresh(['payments', 'subscription']);
    }

    public function test_revenue_breakdown_and_exact_decimal_calculations(): void
    {
        // Order 1: Product Value 100, Closing Value 80 (Discount 20), Verified Payment 80
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 80.00, 80.00);

        // Order 2: Product Value 1000, Closing Value 900 (Discount 100), Partial Payment 400, Pending 500
        $this->createOrder($this->teamA, $this->employeeA, $this->productElite, 1000.00, 900.00, 400.00, 500.00);

        // Order 3: Product Value 100, Closing Value 100, Refunded Payment 50
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, null, null, 50.00);

        $breakdown = $this->analytics->revenueBreakdown();

        // Product value: 100 + 1000 + 100 = 1200.00
        $this->assertSame('1200.00', $breakdown['product_value']);
        // Closing value: 80 + 900 + 100 = 1080.00
        $this->assertSame('1080.00', $breakdown['closing_value']);
        // Amount received (verified collected): 80 + 400 = 480.00 (refunded and pending excluded)
        $this->assertSame('480.00', $breakdown['amount_received']);
        // Pending amount: 1080.00 - 480.00 = 600.00
        $this->assertSame('600.00', $breakdown['pending_amount']);
        // Discounts: 1200.00 - 1080.00 = 120.00
        $this->assertSame('120.00', $breakdown['discounts']);
        // Refunds: 50.00
        $this->assertSame('50.00', $breakdown['refunds']);
    }

    public function test_admin_analytics_overview_endpoint(): void
    {
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, 100.00);

        $response = $this->getJson(
            '/api/v1/admin/analytics/overview',
            $this->actingAsToken($this->adminToken($this->admin))
        );

        $response->assertOk()
            ->assertJsonPath('verified_revenue', '100.00')
            ->assertJsonPath('total_orders', 1)
            ->assertJsonPath('active_subscriptions', 1)
            ->assertJsonPath('pending_collections', '0.00');
    }

    public function test_admin_analytics_revenue_endpoint(): void
    {
        $this->createOrder($this->teamA, $this->employeeA, $this->productElite, 1000.00, 850.00, 500.00);

        $response = $this->getJson(
            '/api/v1/admin/analytics/revenue',
            $this->actingAsToken($this->adminToken($this->admin))
        );

        $response->assertOk()
            ->assertJsonPath('product_value', '1000.00')
            ->assertJsonPath('closing_value', '850.00')
            ->assertJsonPath('amount_received', '500.00')
            ->assertJsonPath('pending_amount', '350.00')
            ->assertJsonPath('discounts', '150.00');
    }

    public function test_product_wise_analytics_is_dynamic_and_includes_all_catalog_products(): void
    {
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, 100.00);

        $response = $this->getJson(
            '/api/v1/admin/analytics/products',
            $this->actingAsToken($this->adminToken($this->admin))
        );

        $response->assertOk();
        $data = $response->json();

        // Both Pro and Elite appear in the report
        $products = collect($data)->pluck('product')->all();
        $this->assertContains('Pro AI 1 Month', $products);
        $this->assertContains('Elite AI 1 Year', $products);

        // Pro has 1 order and 100.00 collected revenue
        $proRow = collect($data)->firstWhere('product', 'Pro AI 1 Month');
        $this->assertSame(1, $proRow['orders']);
        $this->assertSame('100.00', $proRow['collected_revenue']);
        $this->assertSame(1, $proRow['active_subscriptions']);

        // Elite has 0 orders and 0.00 revenue
        $eliteRow = collect($data)->firstWhere('product', 'Elite AI 1 Year');
        $this->assertSame(0, $eliteRow['orders']);
        $this->assertSame('0.00', $eliteRow['collected_revenue']);
    }

    public function test_employee_and_team_analytics_endpoints(): void
    {
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, 100.00);
        $this->createOrder($this->teamB, $this->employeeB, $this->productElite, 1000.00, 1000.00, 600.00);

        // Admin checks employees
        $empResponse = $this->getJson(
            '/api/v1/admin/analytics/employees',
            $this->actingAsToken($this->adminToken($this->admin))
        );

        $empResponse->assertOk();
        $empData = collect($empResponse->json());
        $empARow = $empData->firstWhere('username', 'emp_alpha');
        $this->assertSame('100.00', $empARow['verified_revenue']);
        $empBRow = $empData->firstWhere('username', 'emp_beta');
        $this->assertSame('600.00', $empBRow['verified_revenue']);

        // Admin checks teams
        $teamResponse = $this->getJson(
            '/api/v1/admin/analytics/teams',
            $this->actingAsToken($this->adminToken($this->admin))
        );

        $teamResponse->assertOk();
        $teamData = collect($teamResponse->json());
        $teamARow = $teamData->firstWhere('team_code', 'TMA');
        $this->assertSame('100.00', $teamARow['revenue']);
        $teamBRow = $teamData->firstWhere('team_code', 'TMB');
        $this->assertSame('600.00', $teamBRow['revenue']);
    }

    public function test_monthly_analytics_endpoint(): void
    {
        $lastMonth = Carbon::now()->subMonth()->format('Y-m-15');
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, 100.00, null, null, $lastMonth);

        $response = $this->getJson(
            '/api/v1/admin/analytics/monthly?months=6',
            $this->actingAsToken($this->adminToken($this->admin))
        );

        $response->assertOk();
        $this->assertNotEmpty($response->json());
    }

    public function test_detailed_reports_endpoints_support_pagination_and_filters(): void
    {
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, 100.00);

        // Orders report
        $this->getJson(
            '/api/v1/admin/reports/orders?payment_status=paid',
            $this->actingAsToken($this->adminToken($this->admin))
        )->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonStructure(['data', 'current_page', 'total']);

        // Payments report
        $this->getJson(
            '/api/v1/admin/reports/payments',
            $this->actingAsToken($this->adminToken($this->admin))
        )->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);

        // Subscriptions report
        $this->getJson(
            '/api/v1/admin/reports/subscriptions',
            $this->actingAsToken($this->adminToken($this->admin))
        )->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
    }

    public function test_team_and_employee_scoped_analytics_and_access_control(): void
    {
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, 100.00);
        $this->createOrder($this->teamB, $this->employeeB, $this->productElite, 1000.00, 1000.00, 750.00);

        // Team A calls team analytics -> sees only Team A data
        $teamAResponse = $this->getJson(
            '/api/v1/team/analytics',
            $this->actingAsToken($this->teamToken($this->teamA))
        );

        $teamAResponse->assertOk()
            ->assertJsonPath('team.team_code', 'TMA')
            ->assertJsonPath('overview.verified_revenue', '100.00');

        // Team A cannot access admin analytics (403)
        $this->getJson(
            '/api/v1/admin/analytics/overview',
            $this->actingAsToken($this->teamToken($this->teamA))
        )->assertForbidden();

        // Employee A calls employee analytics -> sees only Employee A data
        $empAResponse = $this->getJson(
            '/api/v1/employee/analytics',
            $this->actingAsToken($this->employeeToken($this->employeeA))
        );

        $empAResponse->assertOk()
            ->assertJsonPath('employee.username', 'emp_alpha')
            ->assertJsonPath('overview.verified_revenue', '100.00');

        // Employee A cannot access admin analytics or team analytics
        $this->getJson(
            '/api/v1/admin/analytics/overview',
            $this->actingAsToken($this->employeeToken($this->employeeA))
        )->assertForbidden();

        $this->getJson(
            '/api/v1/team/analytics',
            $this->actingAsToken($this->employeeToken($this->employeeA))
        )->assertForbidden();
    }

    public function test_date_filters_isolate_timeframes(): void
    {
        // Order from yesterday
        $yesterday = Carbon::yesterday()->format('Y-m-d H:i:s');
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, 100.00, null, null, $yesterday);

        // Order from today
        $today = Carbon::today()->format('Y-m-d H:i:s');
        $this->createOrder($this->teamA, $this->employeeA, $this->productElite, 1000.00, 1000.00, 1000.00, null, null, $today);

        // Filter: today
        $todayOverview = $this->analytics->overview(['range' => 'today']);
        $this->assertSame(1, $todayOverview['total_orders']);
        $this->assertSame('1000.00', $todayOverview['verified_revenue']);

        // Filter: all time
        $allOverview = $this->analytics->overview();
        $this->assertSame(2, $allOverview['total_orders']);
        $this->assertSame('1100.00', $allOverview['verified_revenue']);
    }

    public function test_admin_dashboard_web_view_renders_dynamic_statistics(): void
    {
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, 100.00);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Total Verified Revenue')
            ->assertSee('100.00')
            ->assertSee('Pending Collections')
            ->assertSee('Dynamic Product Analytics')
            ->assertSee('Team Performance');
    }

    public function test_admin_reports_web_view_renders_interactive_reports(): void
    {
        $this->createOrder($this->teamA, $this->employeeA, $this->productPro, 100.00, 100.00, 100.00);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Sales Analytics & Reports', false)
            ->assertSee('Financial Ledger Breakdown')
            ->assertSee('Overview & Revenue', false);
    }
}
