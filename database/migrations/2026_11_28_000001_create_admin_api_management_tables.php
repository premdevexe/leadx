<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('LeadX Android App');
            $table->string('base_url')->default('/api/v1');
            $table->string('version', 20)->default('v1');
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->unsignedInteger('key_expiry_days')->default(90);
            $table->text('ip_allowlist')->nullable();
            $table->boolean('maintenance_mode')->default(false);
            $table->timestamps();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_application_id')->constrained('api_applications')->cascadeOnDelete();
            $table->string('name')->default('Production Android App Key');
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 32);
            $table->string('status', 20)->default('active'); // active, disabled, revoked, expired
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_type')->nullable();
            $table->timestamps();

            $table->index(['api_application_id', 'status']);
        });

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 64)->index();
            $table->string('endpoint', 255)->index();
            $table->string('method', 10)->index();
            $table->unsignedSmallInteger('status_code')->index();
            $table->unsignedInteger('response_time_ms');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->index();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('api_applications');
    }
};
