<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_api_integrations_page_with_all_tabs()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.api-settings.index'));

        $response->assertStatus(200);
        $response->assertSee('API Integrations & Credentials');
        $response->assertSee('Pusher Beams');
        $response->assertSee('Google OAuth');
        $response->assertSee('WhatsApp API');

        // Check tabs and panels exist
        $response->assertSee('id="tab-pusher"', false);
        $response->assertSee('id="tab-google"', false);
        $response->assertSee('id="tab-whatsapp"', false);
    }

    public function test_unauthenticated_visitor_is_redirected()
    {
        $response = $this->get(route('admin.api-settings.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_employee_cannot_access_admin_api_settings()
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $response = $this->actingAs($employee, 'employee')->get(route('admin.api-settings.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_update_pusher_credentials_dynamically()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.api-settings.pusher.update'), [
            'instance_id' => 'pusher-test-instance-12345',
            'server_key'  => 'super-secret-pusher-key-999',
            'cluster'     => 'eu1',
            'is_enabled'  => 1,
        ]);

        $response->assertRedirect(route('admin.api-settings.index'));
        $response->assertSessionHas('status', 'Pusher Beams configuration updated successfully.');

        $pusher = ApiSetting::getGroup(ApiSetting::GROUP_PUSHER);
        $this->assertEquals('pusher-test-instance-12345', $pusher['instance_id']);
        $this->assertEquals('super-secret-pusher-key-999', $pusher['server_key']);
        $this->assertEquals('eu1', $pusher['cluster']);

        // Check runtime config is updated dynamically
        $this->assertEquals('pusher-test-instance-12345', config('services.pusher.instance_id'));
        $this->assertEquals('super-secret-pusher-key-999', config('services.pusher.server_key'));
        $this->assertEquals('eu1', config('services.pusher.options.cluster'));
    }

    public function test_admin_can_update_google_oauth_credentials()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.api-settings.google.update'), [
            'client_id'     => 'custom-google-client-id.apps.googleusercontent.com',
            'client_secret' => 'custom-google-client-secret-xyz',
            'redirect_uri'  => 'https://leadx.test/api/v1/google/calendar/callback',
            'is_enabled'    => 1,
        ]);

        $response->assertRedirect(route('admin.api-settings.index'));
        $response->assertSessionHas('status', 'Google OAuth & Calendar API configuration updated successfully.');

        $google = ApiSetting::getGroup(ApiSetting::GROUP_GOOGLE);
        $this->assertEquals('custom-google-client-id.apps.googleusercontent.com', $google['client_id']);
        $this->assertEquals('custom-google-client-secret-xyz', $google['client_secret']);
        $this->assertEquals('https://leadx.test/api/v1/google/calendar/callback', $google['redirect_uri']);

        // Check runtime config
        $this->assertEquals('custom-google-client-id.apps.googleusercontent.com', config('google.client_id'));
        $this->assertEquals('custom-google-client-secret-xyz', config('google.client_secret'));
        $this->assertEquals('https://leadx.test/api/v1/google/calendar/callback', config('google.redirect_uri'));
    }

    public function test_admin_can_update_whatsapp_settings()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.api-settings.whatsapp.update'), [
            'provider'             => 'click_to_chat',
            'base_url'             => 'https://api.whatsapp.com/send',
            'default_country_code' => '91',
            'message_template'     => 'Hello {customer_name}, your order #{order_number} is processed!',
            'is_enabled'           => 1,
        ]);

        $response->assertRedirect(route('admin.api-settings.index'));
        $response->assertSessionHas('status', 'WhatsApp integration settings updated successfully.');

        $whatsapp = ApiSetting::getGroup(ApiSetting::GROUP_WHATSAPP);
        $this->assertEquals('https://api.whatsapp.com/send', $whatsapp['base_url']);

        // Check runtime config
        $this->assertEquals('https://api.whatsapp.com/send', config('leadx.whatsapp.base_url'));
    }

}
