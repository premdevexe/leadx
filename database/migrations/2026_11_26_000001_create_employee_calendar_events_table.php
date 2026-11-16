<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal Google Calendar events created through
 * POST /api/v1/employee/calendar/events (Phase: Employee Panel REST API).
 *
 * LeadX stores a local row per remote event so it can:
 *  - address PUT/DELETE /employee/calendar/events/{id} by a stable local id
 *    (never by guessing a Google event id),
 *  - enforce strict ownership (an employee may only touch rows whose
 *    employee_id is their own), and
 *  - retry a failed remote write without creating a duplicate event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete();
            // The remote Google event id; null while a write is pending.
            $table->string('google_event_id')->nullable()->index();
            $table->string('summary');
            $table->text('description')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at')->nullable();
            // synced | error | deleted
            $table->string('status')->default('synced');
            $table->string('last_message', 255)->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_calendar_events');
    }
};
