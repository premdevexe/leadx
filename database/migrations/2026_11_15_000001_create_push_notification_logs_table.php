<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * History of Beams web push notifications sent from the admin panel.
     *
     * status is 'sent' or 'failed' — the log only records that a publish
     * request was made and what the API returned; it never claims delivery
     * to a device.
     */
    public function up(): void
    {
        Schema::create('push_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('title', 100);
            $table->text('body');
            $table->string('interests', 255);
            $table->string('publish_id', 64)->nullable();
            $table->string('status', 20)->default('sent');
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_logs');
    }
};
