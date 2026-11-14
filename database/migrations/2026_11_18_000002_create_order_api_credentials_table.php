<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-order API key delivery (order fulfillment, not provider config).
 *
 * A customer's own API key is entered manually by an authorized user when
 * delivering an order. It is stored Laravel-encrypted (same convention as
 * the provider credential in `api_credentials` and the Google OAuth tokens)
 * so the database only ever holds ciphertext; keys must stay retrievable,
 * which is why they are encrypted, never hashed.
 *
 * One row per order (unique order_id): replacing the key updates the row in
 * place and re-records who did it. The audit trail is carried by
 * `updated_by_id` / `updated_by_type` (who last wrote the key) plus
 * `created_at` / `updated_at` (when); the plaintext key itself is never
 * audited and is excluded from every log payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_api_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            // The customer's key, Laravel-encrypted at rest (encrypted cast).
            $table->string('key', 2048)->nullable();
            // Who entered / last replaced the key. Admins live in their own
            // table; employees are users. Both are referenced softly so a
            // retired actor never blocks the credential row.
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->string('updated_by_type', 40)->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('sales_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_api_credentials');
    }
};
