<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-employee Google Calendar OAuth connections (Phase 08, Module 2).
 *
 * Each employee links their OWN Google account — never the team's shared
 * login. The employee (users.id) is the only principal this table attaches
 * to; there is deliberately no team_id or admin_id column, which makes it
 * structurally impossible to connect a shared/team login to a personal
 * calendar.
 *
 * Security posture:
 * - The refresh_token and access_token are stored encrypted at rest
 *   (encrypted cast) so a DB dump alone does not yield usable credentials.
 * - Neither token is ever exposed through API responses (see
 *   EmployeeGoogleConnectionResource).
 * - The `state` parameter used to validate the OAuth callback is kept only
 *   transiently (in session/cache) and is NOT persisted here, to prevent
 *   CSRF and replay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_google_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->unique();
            $table->string('google_email')->nullable()->index();
            $table->string('google_user_id', 64)->nullable()->index();
            $table->string('calendar_id')->nullable();
            // Encrypted at rest via the model's 'encrypted' cast.
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_token_expires_at')->nullable();
            // Connected | revoked | disconnected | error
            $table->string('status', 20)->default('connected')->index();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_google_connections');
    }
};
