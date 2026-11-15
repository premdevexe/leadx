<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\EmailSetting;
use App\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_email_management_page_with_all_tabs()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.email.index'));

        $response->assertStatus(200);
        $response->assertSee('Email Management');
        $response->assertSee('SMTP Configuration');
        $response->assertSee('Notification Events');
        $response->assertSee('Templates');
        $response->assertSee('Expiry Reminders');
        $response->assertSee('Email Logs');

        // Check tabs and switchTab script are present in response
        $response->assertSee('id="tab-smtp"', false);
        $response->assertSee('id="tab-events"', false);
        $response->assertSee('id="tab-templates"', false);
        $response->assertSee('id="tab-reminders"', false);
        $response->assertSee('id="tab-logs"', false);
        $response->assertSee('function switchTab', false);
    }

    public function test_unauthenticated_user_cannot_access_email_management()
    {
        $response = $this->get(route('admin.email.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_update_smtp_settings()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.email.smtp.update'), [
            'smtp_host'          => 'smtp.mailtrap.io',
            'smtp_port'          => 2525,
            'smtp_username'      => 'mailtrap_user',
            'smtp_password'      => 'secret123',
            'smtp_encryption'    => 'tls',
            'from_email'         => 'noreply@leadx.test',
            'from_name'          => 'LeadX Test System',
            'connection_timeout' => 15,
        ]);

        $response->assertRedirect(route('admin.email.index'));
        $response->assertSessionHas('status', 'SMTP settings saved.');

        $payload = EmailSetting::payload();
        $this->assertEquals('smtp.mailtrap.io', $payload['smtp_host']);
        $this->assertEquals(2525, $payload['smtp_port']);
    }

    public function test_admin_can_update_expiry_reminders()
    {
        $admin = Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->put(route('admin.email.reminders.update'), [
            'reminder_days' => [7, 3, 1],
            'reminder_time' => '09:00',
        ]);

        $response->assertRedirect(route('admin.email.index'));
        $response->assertSessionHas('status', 'Expiry reminder settings saved.');
    }
}
