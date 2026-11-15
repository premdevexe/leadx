<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable(); // encrypted JSON payload
            $table->timestamps();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();          // e.g. order.confirmation
            $table->string('name', 150);                    // human label
            $table->string('subject');                      // supports {{placeholders}}
            $table->text('body');                           // supports {{placeholders}}
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);  // bundled template (soft-locked)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'key']);
        });

        Schema::create('email_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 100)->unique();     // e.g. order.created
            $table->string('label', 150);
            $table->string('category', 50);                 // customer | team | employee | admin
            $table->string('template_key', 100);            // FK-ish: references email_templates.key
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_enabled']);
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_email', 255);
            $table->string('event_key', 100)->index();      // e.g. order.created
            $table->string('template_key', 100);
            $table->string('subject');
            $table->text('body_preview')->nullable();        // first 500 chars for debugging
            $table->unsignedBigInteger('related_order_id')->nullable()->index();
            $table->unsignedBigInteger('related_subscription_id')->nullable()->index();
            $table->unsignedBigInteger('related_customer_id')->nullable()->index();
            $table->string('status', 20)->default('pending'); // pending | processing | sent | failed
            $table->string('error_summary', 500)->nullable();
            $table->unsignedBigInteger('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['related_order_id', 'event_key']);
        });

        Schema::create('email_reminder_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->string('type', 50);                    // expiry | renewal
            $table->unsignedInteger('days_before');         // e.g. 7, 3, 1
            $table->timestamp('scheduled_at');              // when the reminder is due
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending | sent | skipped
            $table->string('skip_reason', 100)->nullable();
            $table->timestamps();

            // One reminder per subscription per type per days_before window.
            $table->unique(['subscription_id', 'type', 'days_before'], 'email_reminder_uniq');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_reminder_tracking');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_notification_preferences');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('email_settings');
    }
};
