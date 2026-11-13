<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ledger of gateway webhook events, the idempotency layer.
     *
     * The gateway's own event id is unique, so a repeated/resent event is
     * recognised and skipped instead of creating duplicate payments or
     * subscriptions.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50);
            $table->string('event_id', 150); // gateway event id
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('event_type', 50)->nullable();
            $table->string('status', 20)->default('received'); // received | processed | ignored
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // Idempotency key: one ledger row per (gateway, event id).
            $table->unique(['gateway', 'event_id']);
            $table->index(['gateway', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
