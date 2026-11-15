<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Team;
use App\Models\User;
use App\Services\OrderCredentialService;
use App\Services\PermissionService;
use App\Services\WhatsappShareService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyDeliveryAndOrderCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_order_requiring_api_key_cannot_complete_without_one(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'api_key_required_snapshot' => true,
            'closing_value_status' => 'Accepted',
            'order_status' => 'pending',
        ]);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.complete', $order));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('API key is required', session('error'));
        $this->assertEquals('pending', $order->fresh()->order_status);
    }

    public function test_order_without_mandatory_api_key_can_complete_freely(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'api_key_required_snapshot' => false,
            'closing_value_status' => 'Accepted',
            'order_status' => 'pending',
        ]);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.complete', $order));
        $response->assertSessionHas('status');
        $this->assertEquals('confirmed', $order->fresh()->order_status);
    }

    public function test_storing_api_key_allows_completion(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);

        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'api_key_required_snapshot' => true,
            'closing_value_status' => 'Accepted',
            'order_status' => 'pending',
        ]);

        // Employee stores customer API key
        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.api-key.store', $order), [
            'api_key' => 'sk_live_customer_key_xyz987',
        ]);
        $response->assertSessionHas('status');

        $this->assertTrue(OrderCredentialService::hasKey($order));

        // Now completion succeeds
        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.complete', $order));
        $response->assertSessionHas('status');
        $this->assertEquals('confirmed', $order->fresh()->order_status);
    }

    public function test_employee_without_complete_permission_cannot_store_key_or_complete(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'api_key_required_snapshot' => false,
        ]);

        // Revoke orders.complete
        app(PermissionService::class)->setEmployeeOverride($employee, 'orders.complete', false);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.api-key.store', $order), [
            'api_key' => 'sk_live_unauthorized_key',
        ]);
        $response->assertStatus(403);

        $response = $this->actingAs($employee, 'employee')->post(route('employee.orders.complete', $order));
        $response->assertStatus(403);
    }

    public function test_whatsapp_click_to_chat_incorporates_key_safely_when_opted_in(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id]);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'order_status' => 'active',
            'customer_phone' => '+15551234567',
            'duration' => '1y',
        ]);
        \App\Models\Subscription::factory()->create([
            'order_id' => $order->id,
            'team_id' => $team->id,
            'status' => 'active',
        ]);

        app(OrderCredentialService::class)->store($order, 'sk_live_secret_whatsapp_test', $employee);

        $shareService = app(WhatsappShareService::class);
        $result = $shareService->prepare($order, true);

        $this->assertTrue($result['eligible']);
        $this->assertTrue($result['api_key_included']);
        $this->assertStringContainsString('sk_live_secret_whatsapp_test', $result['message']);
        $this->assertStringStartsWith('https://wa.me/15551234567', $result['url']);
    }
}
