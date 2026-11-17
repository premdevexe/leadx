<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('target')->default('all'); // all, hello, user, or custom interest
            $table->string('target_value')->nullable(); // user_id or specific interest
            $table->string('action_url')->nullable(); // optional deep-link route
            $table->string('publish_id')->nullable(); // Pusher publishId
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
