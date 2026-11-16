<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallationWizardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (File::exists(storage_path('app/installed.lock'))) {
            File::move(storage_path('app/installed.lock'), storage_path('app/installed.lock.bak'));
        }
    }

    protected function tearDown(): void
    {
        if (File::exists(storage_path('app/installed.lock.bak'))) {
            File::move(storage_path('app/installed.lock.bak'), storage_path('app/installed.lock'));
        }
        parent::tearDown();
    }

    public function test_requirements_page_renders_successfully(): void
    {
        $response = $this->get(route('install.requirements'));
        $response->assertStatus(200);
        $response->assertSee('LeadX Installation');
        $response->assertSee('Environment Requirements');
    }

    public function test_environment_page_renders_successfully(): void
    {
        $response = $this->get(route('install.environment'));
        $response->assertStatus(200);
        $response->assertSee('Application Configuration');
        $response->assertSee('Application Name');
        $response->assertSee('Application URL');
        $response->assertSee('Timezone');
        $response->assertSee('Default Currency Code');
        $response->assertSee('Currency Symbol');
    }

    public function test_environment_page_renders_with_validation_errors(): void
    {
        // Submitting invalid data should flash validation errors and redirect back
        $response = $this->post(route('install.save-environment'), [
            'app_name' => '',
            'app_url' => 'not-a-valid-url',
            'timezone' => '',
            'currency' => '',
        ]);

        $response->assertSessionHasErrors(['app_name', 'app_url', 'timezone', 'currency']);

        // Following redirect to environment page should display errors without crashing
        $followResponse = $this->get(route('install.environment'));
        $followResponse->assertStatus(200);
        $followResponse->assertSee('Please fix the following errors');
    }

    public function test_database_page_renders_successfully(): void
    {
        $response = $this->get(route('install.database'));
        $response->assertStatus(200);
        $response->assertSee('Database Configuration');
        $response->assertSee('Database Host');
        $response->assertSee('Database Name');
    }

    public function test_migrate_page_renders_successfully(): void
    {
        $response = $this->get(route('install.migrate'));
        $response->assertStatus(200);
        $response->assertSee('Database Installation');
        $response->assertSee('Run Migrations');
    }

    public function test_admin_page_renders_successfully(): void
    {
        $response = $this->get(route('install.admin'));
        $response->assertStatus(200);
        $response->assertSee('Create Super Admin');
        $response->assertSee('Full Name');
        $response->assertSee('Email Address');
    }

    public function test_config_page_renders_successfully(): void
    {
        $response = $this->get(route('install.config'));
        $response->assertStatus(200);
        $response->assertSee('System Configuration');
        $response->assertSee('SMTP / Email');
        $response->assertSee('Pusher');
    }

    public function test_verify_page_renders_successfully(): void
    {
        $response = $this->get(route('install.verify'));
        $response->assertStatus(200);
        $response->assertSee('Final Verification');
    }

    public function test_app_css_asset_exists_and_contains_design_tokens(): void
    {
        $this->assertFileExists(public_path('assets/css/app.css'));

        $css = File::get(public_path('assets/css/app.css'));
        $this->assertStringContainsString('--c-neutral-900', $css);
        $this->assertStringContainsString('--sp-0.5', $css);
        $this->assertStringContainsString('--sp-4', $css);
        $this->assertStringContainsString('.auth-card-wide', $css);
        $this->assertStringContainsString('.btn-primary', $css);
        $this->assertStringContainsString('.data-table', $css);
    }
}
