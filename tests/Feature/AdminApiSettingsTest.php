<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApiSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
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
        $response->assertSee('Payment Gateway');
        $response->assertSee('Outbound Webhooks');
        $response->assertSee('API Access Tokens');

        // Check tabs and panels exist
        $response->assertSee('id="tab-pusher"', false);
        $response->assertSee('id="tab-google"', false);
        $response->assertSee('id="tab-whatsapp"', false);
        $response->assertSee('id="tab-gateway"', false);
        $response->assertSee('id="tab-webhooks"', false);
        $response->assertSee('id="tab-tokens"', false);
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
            'redirect_uri'  => 'https://leadx.test/employee/calendar/connect',
            'is_enabled'    => 1,
        ]);

        $response->assertRedirect(route('admin.api-settings.index'));
        $response->assertSessionHas('status', 'Google OAuth & Calendar API configuration updated successfully.');

        $google = ApiSetting::getGroup(ApiSetting::GROUP_GOOGLE);
        $this->assertEquals('custom-google-client-id.apps.googleusercontent.com', $google['client_id']);
        $this->assertEquals('custom-google-client-secret-xyz', $google['client_secret']);

        // Check runtime config
        $this->assertEquals('custom-google-client-id.apps.googleusercontent.com', config('google.client_id'));
        $this->assertEquals('custom-google-client-secret-xyz', config('google.client_secret'));
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
        $this->assertEquals('91', $whatsapp['default_country_code']);

        // Check runtime config
        $this->assertEquals('https://api.whatsapp.com/send', config('leadx.whatsapp.base_url'));
        $this->assertEquals('91', config('leadx.whatsapp.default_country_code'));
    }

    public function test_admin_can_update_payment_gateway_settings()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.api-settings.gateway.update'), [
            'driver'         => 'razorpay',
            'environment'    => 'live',
            'key_id'         => 'rzp_live_1234567890',
            'secret_key'     => 'razorpay_secret_key_abcdef',
            'webhook_secret' => 'whsec_testing_signature_999',
            'is_enabled'     => 1,
        ]);

        $response->assertRedirect(route('admin.api-settings.index'));
        $response->assertSessionHas('status', 'Payment Gateway API credentials updated successfully.');

        $gateway = ApiSetting::getGroup(ApiSetting::GROUP_GATEWAY);
        $this->assertEquals('razorpay', $gateway['driver']);
        $this->assertEquals('live', $gateway['environment']);
        $this->assertEquals('rzp_live_1234567890', $gateway['key_id']);
        $this->assertEquals('razorpay_secret_key_abcdef', $gateway['secret_key']);
        $this->assertEquals('whsec_testing_signature_999', $gateway['webhook_secret']);

        // Check runtime config
        $this->assertEquals('razorpay', config('leadx.gateway.driver'));
        $this->assertEquals('whsec_testing_signature_999', config('leadx.gateway.secret'));
    }

    public function test_admin_can_update_outbound_webhook_settings()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.api-settings.webhook.update'), [
            'endpoint_url' => 'https://example.com/webhooks/leadx',
            'secret_token' => 'my-webhook-secret-token',
            'events'       => ['order.created', 'payment.verified'],
            'is_enabled'   => 1,
        ]);

        $response->assertRedirect(route('admin.api-settings.index'));
        $response->assertSessionHas('status', 'Outbound Webhooks configuration saved successfully.');

        $webhook = ApiSetting::getGroup(ApiSetting::GROUP_WEBHOOK);
        $this->assertEquals('https://example.com/webhooks/leadx', $webhook['endpoint_url']);
        $this->assertEquals('my-webhook-secret-token', $webhook['secret_token']);
        $this->assertEquals(['order.created', 'payment.verified'], $webhook['events']);
        $this->assertTrue($webhook['is_enabled']);
    }

    public function test_admin_can_generate_and_revoke_sanctum_api_token()
    {
        $admin = Admin::factory()->create();

        // Generate token
        $response = $this->actingAs($admin, 'admin')->post(route('admin.api-settings.tokens.create'), [
            'token_name' => 'Automated Test Token',
            'abilities'  => 'orders.read, payments.read',
        ]);

        $response->assertRedirect(route('admin.api-settings.index'));
        $response->assertSessionHas('status', 'New API Token created successfully.');
        $response->assertSessionHas('new_token');

        $this->assertDatabaseHas('personal_access_tokens', [
            'name'           => 'Automated Test Token',
            'tokenable_type' => Admin::class,
            'tokenable_id'   => $admin->id,
        ]);

        $token = PersonalAccessToken::where('name', 'Automated Test Token')->first();
        $this->assertNotNull($token);
        $this->assertEquals(['orders.read', 'payments.read'], $token->abilities);

        // Revoke token
        $revokeResponse = $this->actingAs($admin, 'admin')->delete(route('admin.api-settings.tokens.revoke', $token));
        $revokeResponse->assertRedirect(route('admin.api-settings.index'));
        $revokeResponse->assertSessionHas('status', 'API Token "Automated Test Token" revoked successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->id,
        ]);
    }
}
