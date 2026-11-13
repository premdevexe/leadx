<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up history ledger (Phase 08, Module 1, rule 8 & 9).
 *
 * Every state transition of a follow-up (completed, rescheduled,
 * cancelled, re-scheduled, outcome recorded) is appended here so the
 * current row can stay "now" while this table stays "always".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('follow_up_id')->index();
            $table->string('status', 20);
            $table->text('outcome')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->string('acted_by_type', 40)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('follow_up_id')->references('id')->on('follow_ups')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_histories');
    }
};
