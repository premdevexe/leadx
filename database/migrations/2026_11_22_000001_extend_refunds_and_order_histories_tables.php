<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Extend refunds table with status tracking and gateway reference
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('status', 50)->default('processed')->after('type');
            $table->string('gateway_reference', 255)->nullable()->after('status');
            $table->timestamp('processed_at')->nullable()->after('gateway_reference');
        });

        // 2. Create configurable refund_statuses table
        Schema::create('refund_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('label', 100);
            $table->string('color', 50)->default('neutral');
            $table->boolean('is_final')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Create order_edit_histories table for Super Admin order audits
        Schema::create('order_edit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('field_name', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('reason');
            $table->timestamps();
        });

        // 4. Create order_closing_value_histories table for review & resubmission tracking
        Schema::create('order_closing_value_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->string('actor_type', 50); // 'admin' or 'employee'
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('status', 50); // 'Requested', 'Accepted', 'Rejected'
            $table->decimal('previous_value', 15, 2)->nullable();
            $table->decimal('proposed_value', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. Create user_ui_preferences table for Admin & Employee dashboard and sidebar ordering/visibility
        Schema::create('user_ui_preferences', function (Blueprint $table) {
            $table->id();
            $table->string('user_type', 50); // 'admin' or 'user'
            $table->unsignedBigInteger('user_id');
            $table->string('category', 50); // 'dashboard_cards' or 'sidebar_menu'
            $table->json('preferences');
            $table->timestamps();

            $table->unique(['user_type', 'user_id', 'category'], 'user_ui_prefs_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_ui_preferences');
        Schema::dropIfExists('order_closing_value_histories');
        Schema::dropIfExists('order_edit_histories');
        Schema::dropIfExists('refund_statuses');

        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn(['status', 'gateway_reference', 'processed_at']);
        });
    }
};
