<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\PermissionPreset;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Models\UserUiPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UiPreferencesAndCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    public function test_super_admin_can_save_and_reset_dashboard_preferences(): void
    {
        $admin = Admin::factory()->create();

        $saveResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.dashboard.preferences.save'), [
                'cards' => [
                    ['key' => 'verified_revenue', 'visible' => true, 'order' => 1],
                    ['key' => 'orders_total', 'visible' => false, 'order' => 2],
                ],
            ]);

        $saveResponse->assertRedirect();
        $prefs = UserUiPreference::getPreferences('admin', $admin->id, 'dashboard_cards');
        $this->assertNotEmpty($prefs);
        $this->assertEquals('verified_revenue', $prefs[0]['key']);
        $this->assertTrue($prefs[0]['visible']);
        $this->assertEquals('orders_total', $prefs[1]['key']);
        $this->assertFalse($prefs[1]['visible']);

        // Reset
        $resetResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.dashboard.preferences.reset'));

        $resetResponse->assertRedirect();
        $this->assertEmpty(UserUiPreference::getPreferences('admin', $admin->id, 'dashboard_cards'));
    }

    public function test_employee_dashboard_renders_9_metric_cards_and_scoped_values(): void
    {
        $team = Team::factory()->create(['team_name' => 'Alpha Sales', 'team_code' => 'ALPHA']);
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        $product = Product::factory()->create(['price' => 500.00]);
        $order = SalesOrder::factory()->create([
            'team_id' => $team->id,
            'assigned_employee_id' => $employee->id,
            'customer_name' => 'Jane Doe',
            'customer_phone' => '+15551234567',
            'product_id' => $product->id,
            'closing_value' => 500.00,
            'payment_status' => 'paid',
        ]);

        Subscription::create([
            'team_id' => $team->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays(15), // expiring in 15 days
        ]);

        $response = $this->actingAs($employee, 'employee')
            ->get(route('employee.dashboard'));

        $response->assertOk();
        $response->assertSee('My Sales Dashboard');
        $response->assertSee('My Total Orders');
        $response->assertSee('My Sales / Closing Value');
        $response->assertSee('My Verified Revenue');
        $response->assertSee('My Customers');
        $response->assertSee('My Active Subscriptions');
        $response->assertSee('My Pending Payments');
        $response->assertSee('Expiring in 30 Days');
        $response->assertSee('My Follow-ups');
        $response->assertSee('Monthly Performance');
    }

    public function test_employee_can_save_and_reset_dashboard_and_sidebar_preferences(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        // Save card preferences
        $cardResponse = $this->actingAs($employee, 'employee')
            ->post(route('employee.dashboard.preferences.save'), [
                'cards' => [
                    ['key' => 'orders_count', 'visible' => true, 'order' => 1],
                    ['key' => 'follow_ups', 'visible' => false, 'order' => 2],
                ],
            ]);

        $cardResponse->assertRedirect();
        $prefs = UserUiPreference::getPreferences('employee', $employee->id, 'dashboard_cards');
        $this->assertEquals('orders_count', $prefs[0]['key']);
        $this->assertFalse($prefs[1]['visible']);

        // Save sidebar preferences
        $sideResponse = $this->actingAs($employee, 'employee')
            ->post(route('employee.sidebar.preferences.save'), [
                'modules' => [
                    ['key' => 'dashboard', 'visible' => true, 'order' => 1],
                    ['key' => 'calendar', 'visible' => true, 'order' => 2],
                ],
            ]);

        $sideResponse->assertRedirect();
        $sidePrefs = UserUiPreference::getPreferences('employee', $employee->id, 'sidebar_modules');
        $this->assertEquals('dashboard', $sidePrefs[0]['key']);
        $this->assertEquals('calendar', $sidePrefs[1]['key']);

        // Reset dashboard
        $resetCards = $this->actingAs($employee, 'employee')
            ->post(route('employee.dashboard.preferences.reset'));
        $resetCards->assertRedirect();
        $this->assertEmpty(UserUiPreference::getPreferences('employee', $employee->id, 'dashboard_cards'));

        // Reset sidebar
        $resetSide = $this->actingAs($employee, 'employee')
            ->post(route('employee.sidebar.preferences.reset'));
        $resetSide->assertRedirect();
        $this->assertEmpty(UserUiPreference::getPreferences('employee', $employee->id, 'sidebar_modules'));
    }

    public function test_employee_profile_displays_info_and_updates_contact_safely(): void
    {
        $team = Team::factory()->create(['team_name' => 'Bravo Team', 'team_code' => 'BRAVO']);
        $preset = PermissionPreset::create(['name' => 'Senior Closer', 'slug' => 'senior_closer', 'description' => 'Top sales rep', 'is_default' => false]);
        $employee = User::factory()->create([
            'name' => 'John Salesman',
            'email' => 'john.sales@leadx.local',
            'phone' => '+19998887777',
            'team_id' => $team->id,
            'preset_id' => $preset->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $showResponse = $this->actingAs($employee, 'employee')
            ->get(route('employee.profile'));

        $showResponse->assertOk();
        $showResponse->assertSee('John Salesman');
        $showResponse->assertSee('Bravo Team');
        $showResponse->assertSee('BRAVO');
        $showResponse->assertSee('Senior Closer');
        $showResponse->assertSee('Active Account');

        // Update personal details and attempt privilege escalation / tampering
        $updateResponse = $this->actingAs($employee, 'employee')
            ->put(route('employee.profile.update'), [
                'name' => 'John Updated',
                'email' => 'john.updated@leadx.local',
                'phone' => '+11112223333',
                // Tamper attempts:
                'role' => 'admin',
                'team_id' => 9999,
                'preset_id' => 9999,
                'status' => 'inactive',
            ]);

        $updateResponse->assertRedirect();
        $employee->refresh();

        // Allowed fields changed
        $this->assertEquals('John Updated', $employee->name);
        $this->assertEquals('john.updated@leadx.local', $employee->email);
        $this->assertEquals('+11112223333', $employee->phone);

        // Protected fields remained intact
        $this->assertEquals('employee', $employee->role);
        $this->assertEquals($team->id, $employee->team_id);
        $this->assertEquals($preset->id, $employee->preset_id);
        $this->assertEquals('active', $employee->status);
    }

    public function test_employee_can_update_password_with_current_password_verification(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create([
            'team_id' => $team->id,
            'password' => Hash::make('SecretPass123'),
            'status' => 'active',
        ]);

        // Wrong current password
        $failResponse = $this->actingAs($employee, 'employee')
            ->put(route('employee.profile.password'), [
                'current_password' => 'WrongPassword',
                'password' => 'NewPassword999',
                'password_confirmation' => 'NewPassword999',
            ]);
        $failResponse->assertSessionHasErrors('current_password');

        // Valid current password
        $successResponse = $this->actingAs($employee, 'employee')
            ->put(route('employee.profile.password'), [
                'current_password' => 'SecretPass123',
                'password' => 'NewPassword999',
                'password_confirmation' => 'NewPassword999',
            ]);

        $successResponse->assertRedirect();
        $employee->refresh();
        $this->assertTrue(Hash::check('NewPassword999', $employee->password));
    }

    public function test_employee_calendar_view_and_expiring_subscriptions_navigation(): void
    {
        $team = Team::factory()->create();
        $employee = User::factory()->create(['team_id' => $team->id, 'status' => 'active']);

        $calResponse = $this->actingAs($employee, 'employee')
            ->get(route('employee.calendar'));

        $calResponse->assertOk();
        $calResponse->assertSee('Google Calendar');

        $expResponse = $this->actingAs($employee, 'employee')
            ->get(route('employee.subscriptions.expiring'));

        $expResponse->assertOk();
    }
}
