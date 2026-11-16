<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Registers Firebase Cloud Messaging device tokens for the Flutter
     * employee app. One row per (employee, device token); the token
     * itself is globally unique (Firebase scopes it to a Firebase app +
     * installation), so it is also unique here.
     *
     * `is_active` lets the server mark a token dead (FCM answered NOT_FOUND)
     * without losing the row; `last_error_at` records when that happened.
     * Deleting an employee cascades their tokens (FK on users.id).
     */
    public function up(): void
    {
        if (Schema::hasTable('employee_device_tokens')) {
            return;
        }

        Schema::create('employee_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('fcm_token', 255)->unique();
            $table->string('platform', 10)->default('android');
            $table->string('device_name', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['employee_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_device_tokens');
    }
};
