<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WhatsApp share activity log.
     *
     * status is 'prepared' or 'recorded' — the system only ever logs that a
     * share link/message was generated or logged; it never claims delivery.
     */
    public function up(): void
    {
        Schema::create('whatsapp_share_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_type', 20)->nullable();
            $table->string('normalized_phone', 30);
            $table->text('message');
            $table->string('status', 20)->default('prepared');
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->foreign('order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_share_logs');
    }
};
