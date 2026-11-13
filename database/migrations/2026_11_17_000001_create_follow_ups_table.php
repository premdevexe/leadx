<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer follow-ups (Phase 08, Module 1 & 3).
 *
 * A follow-up always points at an existing verified CRM customer and an
 * authorized employee. The Google Calendar columns (Module 3) live on the
 * same row: creating a follow-up optionally creates a calendar event on the
 * assigned employee's connected account, and the sync bookkeeping is kept
 * here so LeadX remains the source of truth even if a Google event is
 * deleted out-of-band.
 *
 * All foreign keys are nullOnDelete on purpose: a customer, team or
 * employee being retired must never cascade-delete follow-up history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->unsignedBigInteger('assigned_employee_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            // Audit trail.
            $table->string('created_by_type', 40)->nullable();
            $table->string('title', 255);
            $table->text('description')->nullable();
            // general | subscription_renewal | payment | technical_support | feedback | retention
            $table->string('follow_up_type', 30)->default('general')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            // scheduled | completed | rescheduled | cancelled | overdue
            $table->string('status', 20)->default('scheduled')->index();
            $table->text('outcome')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            // low | medium | high | urgent
            $table->string('priority', 20)->default('medium')->index();
            // Optional order link (Module 3 event context: "LeadX Order ID when relevant").
            $table->unsignedBigInteger('order_id')->nullable()->index();

            // Google Calendar sync bookkeeping (Module 3).
            // google_event_id is what makes retried requests idempotent: a
            // follow-up that already has an event is never re-created.
            $table->string('google_event_id', 255)->nullable()->index();
            // synced | not_synced | sync_error | not_connected
            $table->string('calendar_sync_status', 20)->nullable()->index();
            $table->string('calendar_sync_message', 500)->nullable();
            $table->timestamp('calendar_synced_at')->nullable();

            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->restrictOnDelete();
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
            $table->foreign('assigned_employee_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('order_id')->references('id')->on('sales_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
