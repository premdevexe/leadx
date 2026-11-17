<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the public_order_shares table.
 *
 * A public share link lets a customer view order status and, when the order
 * is share-eligible, the delivered API key, without logging in. The token is
 * the only secret protecting the page (like a password-reset link): 40
 * random characters, created by an authorized actor, revocable and optional
 * time-bounded.
 *
 * Security notes:
 * - `include_api_key` is a per-share opt-in; it is honored ONLY when the
 *   order is share-eligible (active order + active subscription), and the
 *   key is the order's encrypted credential decrypted on the fly.
 * - `revoked_at` kills the link without deleting the audit trail.
 * - `expires_at` is nullable: null means "no expiry".
 * - `created_by` / `revoked_by` are polymorphic (Admin, User, Team) so the
 *   full actor of every lifecycle event is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_order_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
            // 40 random characters — the link is only as strong as this.
            $table->string('token', 64)->unique();
            // Polymorphic creator: Admin, User (employee) or Team.
            $table->nullableMorphs('created_by');
            $table->boolean('include_api_key')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->nullableMorphs('revoked_by');
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_order_shares');
    }
};
