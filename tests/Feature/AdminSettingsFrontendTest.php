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
            ->assertSee('Site Name', false)
            ->assertSee('Currency', false)
            ->assertSee('Logo & Favicon Upload Center', false)
            ->assertSee('Primary Site Logo', false)
            ->assertSee('Transparent Logo', false)
            ->assertSee('Transparent Box', false)
            ->assertSee('Browser Favicon', false)
            ->assertSee('enctype="multipart/form-data"', false)
            // Default currency code is USD; its option must be selected.
            ->assertSee('<option value="USD" selected>$ — USD</option>', false);
    }

    public function test_admin_can_upload_primary_logo_transparent_logo_and_favicon(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $logo = \Illuminate\Http\UploadedFile::fake()->create('custom_logo.png', 20, 'image/png');
        $transparentLogo = \Illuminate\Http\UploadedFile::fake()->create('custom_transparent.png', 20, 'image/png');
        $favicon = \Illuminate\Http\UploadedFile::fake()->create('custom_favicon.ico', 15, 'image/x-icon');

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.settings.update'), [
                'currency' => 'USD',
                'logo' => $logo,
                'logo_transparent' => $transparentLogo,
                'favicon' => $favicon,
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('status');

        $logoPath = Setting::logoPath();
        $transparentPath = Setting::logoTransparentPath();
        $faviconPath = Setting::faviconPath();

        $this->assertNotNull($logoPath);
        $this->assertNotNull($transparentPath);
        $this->assertNotNull($faviconPath);

        $this->assertFileExists(public_path($logoPath));
        $this->assertFileExists(public_path($transparentPath));
        $this->assertFileExists(public_path($faviconPath));

        // Verify the uploaded logo appears in the top header / layout
        $dashboardResponse = $this->actingAs($this->admin(), 'admin')->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();
        $dashboardResponse->assertSee($transparentPath);

        // Clean up test files
        @unlink(public_path($logoPath));
        @unlink(public_path($transparentPath));
        @unlink(public_path($faviconPath));
    }

    public function test_admin_can_remove_uploaded_branding_assets(): void
    {
        $uploadDir = public_path('uploads/branding');
        if (! file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $dummyLogo = 'uploads/branding/dummy_logo.png';
        $dummyTransparent = 'uploads/branding/dummy_transparent.png';
        $dummyFavicon = 'uploads/branding/dummy_favicon.ico';

        file_put_contents(public_path($dummyLogo), 'dummy-content');
        file_put_contents(public_path($dummyTransparent), 'dummy-content');
        file_put_contents(public_path($dummyFavicon), 'dummy-content');

        Setting::setLogo($dummyLogo);
        Setting::setLogoTransparent($dummyTransparent);
        Setting::setFavicon($dummyFavicon);

        $this->assertSame($dummyLogo, Setting::logoPath());

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.settings.update'), [
                'currency' => 'USD',
                'remove_logo' => 1,
                'remove_logo_transparent' => 1,
                'remove_favicon' => 1,
            ])
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('status');

        $this->assertNull(Setting::logoPath());
        $this->assertNull(Setting::logoTransparentPath());
        $this->assertNull(Setting::faviconPath());

        $this->assertFileDoesNotExist(public_path($dummyLogo));
        $this->assertFileDoesNotExist(public_path($dummyTransparent));
        $this->assertFileDoesNotExist(public_path($dummyFavicon));
    }

    public function test_invalid_logo_file_type_is_rejected(): void
    {
        $badFile = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin(), 'admin')
            ->put(route('admin.settings.update'), [
                'currency' => 'USD',
                'logo' => $badFile,
            ])
            ->assertSessionHasErrors('logo');
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
