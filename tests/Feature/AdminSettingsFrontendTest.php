<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingsFrontendTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): Admin
    {
        return Admin::factory()->create();
    }

    public function test_settings_page_renders_site_section_with_defaults(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('Site name', false)
            ->assertSee('Currency', false)
            // Default currency code is USD; its option must be selected.
            ->assertSee('<option value="USD" selected>$ — USD</option>', false);
    }

    public function test_admin_can_set_site_name_and_currency(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.settings.update'), [
                'site_name' => 'Acme Sales',
                'currency' => 'INR',
                'payment_methods' => ['upi', 'cash'],
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('status');

        $this->assertSame('Acme Sales', Setting::siteName());
        $this->assertSame('INR', Setting::currency());
        $this->assertSame('₹', Setting::currencySymbol());
        $this->assertSame(['upi', 'cash'], Setting::stringList(Setting::KEY_PAYMENT_METHODS, []));
    }

    public function test_empty_site_name_reverts_to_default(): void
    {
        Setting::setSiteName('Custom Name');
        Setting::setSiteName('');

        $this->assertSame('LeadX', Setting::siteName());
    }

    public function test_invalid_currency_is_rejected(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.settings.update'), [
                'currency' => 'DOGE',
            ])
            ->assertSessionHasErrors('currency');

        $this->assertSame('USD', Setting::currency());
    }

    public function test_unauthenticated_cannot_update_settings(): void
    {
        $this->put(route('admin.settings.update'), ['currency' => 'INR'])
            ->assertStatus(302);

        $this->assertSame('USD', Setting::currency());
    }
}
