<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\RefundStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundStatusConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RefundStatusSeeder::class);
    }

    public function test_super_admin_can_view_and_create_refund_status(): void
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.settings.refund-statuses'));

        $response->assertOk();
        $response->assertSee('Refund Statuses');

        // Create new status
        $storeResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.settings.refund-statuses.store'), [
                'label' => 'Chargeback Under Review',
                'slug' => 'chargeback_review',
                'color' => 'purple',
                'is_final' => '0',
            ]);

        $storeResponse->assertRedirect();
        $this->assertDatabaseHas('refund_statuses', [
            'slug' => 'chargeback_review',
            'label' => 'Chargeback Under Review',
            'color' => 'purple',
        ]);
    }

    public function test_super_admin_can_update_refund_status(): void
    {
        $admin = Admin::factory()->create();
        $status = RefundStatus::where('slug', 'requested')->first();
        $this->assertNotNull($status);

        $updateResponse = $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.refund-statuses.update', $status), [
                'label' => 'Pending Executive Review',
                'color' => 'amber',
                'is_active' => '1',
            ]);

        $updateResponse->assertRedirect();
        $status->refresh();
        $this->assertEquals('Pending Executive Review', $status->label);
        $this->assertEquals('amber', $status->color);
    }

    public function test_employee_cannot_access_refund_statuses(): void
    {
        $employee = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($employee, 'employee')
            ->get(route('admin.settings.refund-statuses'));

        $response->assertRedirect(route('admin.login'));
    }
}
